<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->license = License::factory()->create();
});

it('can be created with factory', function (): void {
    expect($this->license)->toBeInstanceOf(License::class);
    expect($this->license->id)->not->toBeNull();
});

it('uses auto-increment primary key and a distinct public uuid', function (): void {
    expect($this->license->id)->toBeInt()->toBeGreaterThan(0);
    expect($this->license->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

it('has one user relationship', function (): void {
    $user = User::factory()->create(['license_id' => $this->license->id]);

    expect($this->license->user)->toBeInstanceOf(User::class);
    expect($this->license->user->id)->toBe($user->id);
});

// NOTE: is_active column does not exist in licenses table
// it('has boolean cast for is_active', function (): void {
//     $license = License::factory()->create(['is_active' => true]);
//
//     expect($license->is_active)->toBeTrue();
//     expect($license->is_active)->toBeInstanceOf('bool');
// });

it('has validity trait', function (): void {
    /** @var TestCase $this */
    expect(method_exists($this->license, 'isValid'))->toBeTrue();
    expect(method_exists($this->license, 'isExpired'))->toBeTrue();
    // getValidityPeriod() does not exist in HasValidity trait
});

it('has validations trait', function (): void {
    /** @var TestCase $this */
    expect(method_exists($this->license, 'getRules'))->toBeTrue();
});

// NOTE: is_active column does not exist in licenses table
// This test is commented out until the migration is created
// it('can check if license is active', function (): void {
//     $activeLicense = License::factory()->create(['is_active' => true]);
//     $inactiveLicense = License::factory()->create(['is_active' => false]);
//
//     expect($activeLicense->is_active)->toBeTrue();
//     expect($inactiveLicense->is_active)->toBeFalse();
// });

it('can be created with specific attributes', function (): void {
    $licenseData = [
        'uuid' => (string) Str::uuid(),
        'valid_from' => now(),
        'valid_to' => now()->addYear(),
    ];

    $license = License::create($licenseData);

    expect($license->valid_from->format('Y-m-d H:i:s'))->toBe($licenseData['valid_from']->format('Y-m-d H:i:s'));
    expect($license->valid_to->format('Y-m-d H:i:s'))->toBe($licenseData['valid_to']->format('Y-m-d H:i:s'));
});

it('can be found by id', function (): void {
    $license = License::factory()->create();

    $foundLicense = License::find($license->id);

    expect($foundLicense->id)->toBe($license->id);
});

// NOTE: is_active column does not exist in licenses table
// it('can be found by active status', function (): void {
//     $activeLicense = License::factory()->create(['is_active' => true]);
//     $inactiveLicense = License::factory()->create(['is_active' => false]);
//
//     $activeLicenses = License::where('is_active', true)->get();
//     $inactiveLicenses = License::where('is_active', false)->get();
//
//     expect($activeLicenses)->toHaveCount(1);
//     expect($inactiveLicenses)->toHaveCount(1);
//     expect($activeLicenses->first()->id)->toBe($activeLicense->id);
//     expect($inactiveLicenses->first()->id)->toBe($inactiveLicense->id);
// });

it('has proper public uuid format', function (): void {
    $license = License::factory()->create();

    expect($license->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

it('can be created without user initially', function (): void {
    $license = License::factory()->create();

    expect($license->user)->toBeNull();
});

it('can have user assigned later', function (): void {
    $license = License::factory()->create();
    $user = User::factory()->create(['license_id' => $license->id]);

    expect($license->fresh()->user)->not->toBeNull();
    expect($license->fresh()->user->id)->toBe($user->id);
});

it('free scope filters licenses without user', function (): void {
    $free_license = License::factory()->create();
    $occupied_license = License::factory()->create();
    User::factory()->create(['license_id' => $occupied_license->id]);

    $free_ids = License::query()->free()->pluck('id')->toArray();

    expect($free_ids)->toContain($free_license->id)
        ->and($free_ids)->not->toContain($occupied_license->id);
});

it('occupied scope filters licenses with user', function (): void {
    $free_license = License::factory()->create();
    $occupied_license = License::factory()->create();
    User::factory()->create(['license_id' => $occupied_license->id]);

    $occupied_ids = License::query()->occupied()->pluck('id')->toArray();

    expect($occupied_ids)->toContain($occupied_license->id)
        ->and($occupied_ids)->not->toContain($free_license->id);
});

it('expired scope filters licenses with past valid_to', function (): void {
    $expired_license = License::factory()->create(['valid_to' => today()->subDay()]);
    $active_license = License::factory()->create(['valid_to' => today()->addDay()]);
    $no_expiry = License::factory()->create(['valid_to' => null]);

    $expired_ids = License::query()->expired()->pluck('id')->toArray();

    expect($expired_ids)->toContain($expired_license->id)
        ->and($expired_ids)->not->toContain($active_license->id)
        ->and($expired_ids)->not->toContain($no_expiry->id);
});

it('getRules returns rules with valid_from and valid_to', function (): void {
    $license = new License;
    $rules = $license->getRules();

    expect($rules)->toHaveKey(License::DEFAULT_RULE)
        ->and($rules[License::DEFAULT_RULE])->toHaveKey('valid_from')
        ->and($rules[License::DEFAULT_RULE])->toHaveKey('valid_to');
});

it('has proper timestamps', function (): void {
    $license = License::factory()->create();

    expect($license->created_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
    expect($license->updated_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
});

it('can be serialized to array', function (): void {
    $license = License::factory()->create();
    $licenseArray = $license->toArray();

    expect($licenseArray)->toHaveKey('id')
        ->and($licenseArray)->toHaveKey('uuid')
        ->and($licenseArray)->toHaveKey('valid_from')
        ->and($licenseArray)->toHaveKey('valid_to')
        ->and($licenseArray)->toHaveKey('created_at')
        ->and($licenseArray)->toHaveKey('updated_at');
});
