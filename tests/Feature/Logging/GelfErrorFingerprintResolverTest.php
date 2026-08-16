<?php

declare(strict_types=1);

use Modules\Core\Logging\GelfErrorFingerprintResolver;
use Modules\Core\Tests\Fixtures\GelfFingerprintExceptionFixture;
use Modules\Core\Tests\Fixtures\GelfFingerprintLogFixture;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * These assertions describe the corrected algorithm (design §7): the line number
 * is not part of the hash, and only value-position numbers normalize away — a
 * bare, meaning-carrying number (a 404 vs a 500) stays distinct.
 */
it('groups the same exception when only value-position detail changes', function (): void {
    $resolver = new GelfErrorFingerprintResolver;

    try {
        GelfFingerprintExceptionFixture::indexingFailure('record id=42');
    } catch (RuntimeException $first_exception) {
        $first = $resolver->resolve(new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'gelf',
            level: Level::Error,
            message: 'Indexing failed',
            context: ['exception' => $first_exception],
            extra: [],
        ));
    }

    try {
        GelfFingerprintExceptionFixture::indexingFailure('record id=991');
    } catch (RuntimeException $second_exception) {
        $second = $resolver->resolve(new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'gelf',
            level: Level::Error,
            message: 'Indexing failed',
            context: ['exception' => $second_exception],
            extra: [],
        ));
    }

    expect($first)->toBe($second);
});

it('separates different exception classes', function (): void {
    $resolver = new GelfErrorFingerprintResolver;

    $runtime = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Failed',
        context: ['exception' => new RuntimeException('boom')],
        extra: [],
    ));

    $logic = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Failed',
        context: ['exception' => new LogicException('boom')],
        extra: [],
    ));

    expect($runtime)->not->toBe($logic);
});

it('uses the root cause for wrapped exceptions', function (): void {
    $resolver = new GelfErrorFingerprintResolver;

    try {
        GelfFingerprintExceptionFixture::indexingFailure('root cause');
    } catch (RuntimeException $root_exception) {
        $wrapped = $resolver->resolve(new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'gelf',
            level: Level::Error,
            message: 'Outer',
            context: [
                'exception' => new RuntimeException('wrapper', 0, $root_exception),
            ],
            extra: [],
        ));

        $root = $resolver->resolve(new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'gelf',
            level: Level::Error,
            message: 'Root',
            context: ['exception' => $root_exception],
            extra: [],
        ));
    }

    expect($wrapped)->toBe($root);
});

it('groups recurring log messages when the changing number is in value position', function (): void {
    $fingerprints = GelfFingerprintLogFixture::fingerprintsForMessages(
        'Document id=100 could not be indexed',
        'Document id=55 could not be indexed',
    );

    expect($fingerprints['first'])->toBe($fingerprints['second']);
});

it('keeps the same fingerprint for the same code location across execution contexts', function (): void {
    $resolver = new GelfErrorFingerprintResolver;
    $job_file = base_path('Modules/AI/app/Jobs/GenerateEmbeddingsJob.php');

    $from_queue = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Embedding failed',
        context: [
            'exception' => [
                'class' => RuntimeException::class,
                'file' => $job_file,
                'line' => 89,
                'message' => 'Model id=12 failed',
            ],
        ],
        extra: [],
    ));

    $from_http = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Embedding failed',
        context: [
            'exception' => [
                'class' => RuntimeException::class,
                'file' => $job_file,
                'line' => 89,
                'message' => 'Model id=99 failed',
            ],
        ],
        extra: [],
    ));

    expect($from_queue)->toBe($from_http);
});

/**
 * The headline correction: a refactor that shifts lines within the same file
 * must not fork the group. Under the old algorithm this asserted the opposite.
 */
it('does not fork by line number within the same file', function (): void {
    $resolver = new GelfErrorFingerprintResolver;
    $job_file = base_path('Modules/AI/app/Jobs/GenerateEmbeddingsJob.php');

    $line_89 = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Failed',
        context: [
            'exception' => [
                'class' => RuntimeException::class,
                'file' => $job_file,
                'line' => 89,
                'message' => 'boom',
            ],
        ],
        extra: [],
    ));

    $line_120 = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Failed',
        context: [
            'exception' => [
                'class' => RuntimeException::class,
                'file' => $job_file,
                'line' => 120,
                'message' => 'boom',
            ],
        ],
        extra: [],
    ));

    expect($line_89)->toBe($line_120);
});

/**
 * The deliberate cost of value-position-only numeric normalization: a bare,
 * meaning-carrying number keeps two errors distinct rather than merging them.
 */
it('keeps a 404 and a 500 in separate groups', function (): void {
    $resolver = new GelfErrorFingerprintResolver;
    $file = base_path('Modules/AI/app/Http/Client.php');

    $make = static fn (string $message): string => (new GelfErrorFingerprintResolver)->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Failed',
        context: ['exception' => ['class' => RuntimeException::class, 'file' => $file, 'line' => 5, 'message' => $message]],
        extra: [],
    ));

    expect($make('Upstream returned status 404'))->not->toBe($make('Upstream returned status 500'));
});
