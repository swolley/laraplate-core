<?php

declare(strict_types=1);

use Modules\Core\Logging\GelfErrorFingerprintResolver;
use Modules\Core\Tests\Fixtures\GelfFingerprintExceptionFixture;
use Modules\Core\Tests\Fixtures\GelfFingerprintLogFixture;
use Monolog\Level;
use Monolog\LogRecord;

it('groups the same exception with different volatile message values from one throw site', function (): void {
    $resolver = new GelfErrorFingerprintResolver;

    try {
        GelfFingerprintExceptionFixture::indexingFailure('User 42 not found in index posts-991');
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
        GelfFingerprintExceptionFixture::indexingFailure('User 7 not found in index posts-12');
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

it('groups recurring log messages with changing numbers from one log site', function (): void {
    $fingerprints = GelfFingerprintLogFixture::fingerprintsForMessages(
        'Document 100 could not be indexed',
        'Document 55 could not be indexed',
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
                'message' => 'Model 12 failed',
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
                'message' => 'Model 99 failed',
            ],
        ],
        extra: [],
    ));

    expect($from_queue)->toBe($from_http);
});

it('separates errors thrown from different lines in the same file', function (): void {
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

    expect($line_89)->not->toBe($line_120);
});
