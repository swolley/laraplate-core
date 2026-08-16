<?php

declare(strict_types=1);

use Modules\Core\Logging\Fingerprint\CollapseSqlState;
use Modules\Core\Logging\Fingerprint\CollapseVolatilePayloads;
use Modules\Core\Logging\Fingerprint\Fingerprinter;
use Modules\Core\Logging\Fingerprint\FingerprintNormalizer;
use Modules\Core\Logging\Fingerprint\StripStackTraces;
use Modules\Core\Logging\Fingerprint\SubstituteNumbersInValuePosition;
use Modules\Core\Logging\Fingerprint\SubstituteUuidIpHex;

it('strips an embedded stack trace', function (): void {
    $message = "Something failed\nStack trace:\n#0 /app/foo.php(12): bar()\n#1 {main}";

    expect((new StripStackTraces)->apply($message))->toBe('Something failed');
});

it('collapses a volatile SQL payload', function (): void {
    $message = 'Integrity constraint violation (SQL: insert into `users` (`email`) values (x@y.z))';

    expect((new CollapseVolatilePayloads)->apply($message))->toContain('(SQL: {sql})')
        ->and((new CollapseVolatilePayloads)->apply($message))->not->toContain('insert into');
});

it('keeps the SQLSTATE code but drops its volatile tail', function (): void {
    $message = "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'shop.widgets' doesn't exist";

    expect((new CollapseSqlState)->apply($message))->toBe('SQLSTATE[42S02]');
});

it('substitutes uuid, ip and long hex', function (): void {
    $rule = new SubstituteUuidIpHex;

    expect($rule->apply('user 550e8400-e29b-41d4-a716-446655440000 from 192.168.1.9'))
        ->toBe('user {uuid} from {ip}')
        ->and($rule->apply('token deadbeefdeadbeefdeadbeefdeadbeef99'))->toBe('token {hex}');
});

it('substitutes numbers only in value position', function (): void {
    $rule = new SubstituteNumbersInValuePosition;

    // Value position collapses; a bare status code stays distinct.
    expect($rule->apply('failed with code = 500'))->toBe('failed with code = {n}')
        ->and($rule->apply("expected id '42'"))->toBe("expected id '{n}'");
});

it('a 404 and a 500 do not collapse into one key', function (): void {
    $fingerprinter = new Fingerprinter(FingerprintNormalizer::default());

    $notFound = $fingerprinter->hash('exception', 'App', 'HttpException', 'app/H.php', 'handle', 'HTTP request returned status 404');
    $serverError = $fingerprinter->hash('exception', 'App', 'HttpException', 'app/H.php', 'handle', 'HTTP request returned status 500');

    expect($notFound)->not->toBe($serverError);
});

it('is stable when only volatile detail and the line change', function (): void {
    $fingerprinter = new Fingerprinter(FingerprintNormalizer::default());

    // The Fingerprinter takes no line argument at all, and volatile ids normalize away.
    $first = $fingerprinter->hash('exception', 'App', 'QueryException', 'app/Repo.php', 'find', 'No query results for id = 17');
    $second = $fingerprinter->hash('exception', 'App', 'QueryException', 'app/Repo.php', 'find', 'No query results for id = 999');

    expect($first)->toBe($second)
        ->and($first)->toHaveLength(16);
});
