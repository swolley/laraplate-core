<?php

declare(strict_types=1);

use Modules\Core\Import\Support\ImportFingerprint;

it('produces a lowercase sha256 hash', function (): void {
    $fingerprint = ImportFingerprint::of(['external_id' => 12, 'name' => 'Esteri']);

    expect($fingerprint)->toMatch('/\A[a-f0-9]{64}\z/');
});

it('ignores key order so a reordered payload is not a change', function (): void {
    $first = ImportFingerprint::of(['name' => 'Esteri', 'external_id' => 12]);
    $second = ImportFingerprint::of(['external_id' => 12, 'name' => 'Esteri']);

    expect($first)->toBe($second);
});

it('ignores key order inside nested payloads', function (): void {
    $first = ImportFingerprint::of(['components' => ['b' => 2, 'a' => 1]]);
    $second = ImportFingerprint::of(['components' => ['a' => 1, 'b' => 2]]);

    expect($first)->toBe($second);
});

it('changes when a value changes', function (): void {
    $before = ImportFingerprint::of(['external_id' => 12, 'name' => 'Esteri']);
    $after = ImportFingerprint::of(['external_id' => 12, 'name' => 'Interni']);

    expect($before)->not->toBe($after);
});

it('reads the public properties of an object payload', function (): void {
    $payload = new stdClass();
    $payload->external_id = 12;
    $payload->name = 'Esteri';

    expect(ImportFingerprint::of($payload))->toBe(ImportFingerprint::of(['external_id' => 12, 'name' => 'Esteri']));
});
