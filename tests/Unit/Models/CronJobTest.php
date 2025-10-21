<?php

declare(strict_types=1);

use Modules\Core\Models\CronJob;

test('cron job model has correct structure', function (): void {
    $reflection = new ReflectionClass(CronJob::class);
    $source = file_get_contents($reflection->getFileName());
    
    // Test fillable attributes
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('\'name\'');
    expect($source)->toContain('\'command\'');
    expect($source)->toContain('\'parameters\'');
    expect($source)->toContain('\'schedule\'');
    expect($source)->toContain('\'description\'');
    expect($source)->toContain('\'is_active\'');
});

test('cron job model uses correct traits', function (): void {
    $reflection = new ReflectionClass(CronJob::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\\Database\\Eloquent\\Factories\\HasFactory');
    expect($traits)->toContain('Modules\\Core\\Locking\\Traits\\HasLocks');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasValidations');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasVersions');
    expect($traits)->toContain('Modules\\Core\\Helpers\\SoftDeletes');
});

test('cron job model has required methods', function (): void {
    $reflection = new ReflectionClass(CronJob::class);
    
    expect($reflection->hasMethod('getRules'))->toBeTrue();
});

test('cron job model has correct method signatures', function (): void {
    $reflection = new ReflectionClass(CronJob::class);
    
    // Test getRules method
    $method = $reflection->getMethod('getRules');
    expect($method->getReturnType()->getName())->toBe('array');
});