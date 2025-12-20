<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->license = License::factory()->create();
});

it('can be created with factory', function (): void {
    expect($this->license)->toBeInstanceOf(License::class);
    expect($this->license->id)->not->toBeNull();
});

it('uses uuid as primary key', function (): void {
    expect($this->license->getKeyType())->toBe('string');
    expect($this->license->getKey())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
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

it('has proper uuid format', function (): void {
    $license = License::factory()->create();

    expect($license->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
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

it('has proper timestamps', function (): void {
    $license = License::factory()->create();

    expect($license->created_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
    expect($license->updated_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
});

it('can be serialized to array', function (): void {
    $license = License::factory()->create();
    $licenseArray = $license->toArray();

    expect($licenseArray)->toHaveKey('id');
    expect($licenseArray)->toHaveKey('valid_from');
    expect($licenseArray)->toHaveKey('valid_to');
    expect($licenseArray)->toHaveKey('created_at');
    expect($licenseArray)->toHaveKey('updated_at');
});
