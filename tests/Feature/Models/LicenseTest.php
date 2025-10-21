<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\License;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

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

it('has boolean cast for is_active', function (): void {
    $license = License::factory()->create(['is_active' => true]);
    
    expect($license->is_active)->toBeTrue();
    expect($license->is_active)->toBeInstanceOf('bool');
});

it('has validity trait', function (): void {
    expect($this->license)->toHaveMethod('isValid');
    expect($this->license)->toHaveMethod('isExpired');
    expect($this->license)->toHaveMethod('getValidityPeriod');
});

it('has validations trait', function (): void {
    expect($this->license)->toHaveMethod('getRules');
});

it('can check if license is active', function (): void {
    $activeLicense = License::factory()->create(['is_active' => true]);
    $inactiveLicense = License::factory()->create(['is_active' => false]);
    
    expect($activeLicense->is_active)->toBeTrue();
    expect($inactiveLicense->is_active)->toBeFalse();
});

it('can be created with specific attributes', function (): void {
    $licenseData = [
        'is_active' => true,
    ];

    $license = License::create($licenseData);

    expectModelAttributes($license, [
        'is_active' => true,
    ]);
});

it('can be found by id', function (): void {
    $license = License::factory()->create();
    
    $foundLicense = License::find($license->id);
    
    expect($foundLicense->id)->toBe($license->id);
});

it('can be found by active status', function (): void {
    $activeLicense = License::factory()->create(['is_active' => true]);
    $inactiveLicense = License::factory()->create(['is_active' => false]);
    
    $activeLicenses = License::where('is_active', true)->get();
    $inactiveLicenses = License::where('is_active', false)->get();
    
    expect($activeLicenses)->toHaveCount(1);
    expect($inactiveLicenses)->toHaveCount(1);
    expect($activeLicenses->first()->id)->toBe($activeLicense->id);
    expect($inactiveLicenses->first()->id)->toBe($inactiveLicense->id);
});

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
    
    expect($license->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($license->updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can be serialized to array', function (): void {
    $license = License::factory()->create(['is_active' => true]);
    $licenseArray = $license->toArray();
    
    expect($licenseArray)->toHaveKey('id');
    expect($licenseArray)->toHaveKey('is_active');
    expect($licenseArray)->toHaveKey('created_at');
    expect($licenseArray)->toHaveKey('updated_at');
    expect($licenseArray['is_active'])->toBeTrue();
});
