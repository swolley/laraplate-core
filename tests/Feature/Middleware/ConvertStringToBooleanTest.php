<?php

declare(strict_types=1);

use Modules\Core\Http\Middleware\ConvertStringToBoolean;

test('middleware has correct class structure', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);

    expect($reflection->getName())->toBe('Modules\Core\Http\Middleware\ConvertStringToBoolean');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->isSubclassOf(Illuminate\Foundation\Http\Middleware\TransformsRequest::class))->toBeTrue();
    expect($reflection->hasMethod('transform'))->toBeTrue();
});

test('middleware transform method has correct signature', function (): void {
    $reflection = new ReflectionMethod(ConvertStringToBoolean::class, 'transform');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->isProtected())->toBeTrue();
});

test('middleware uses correct imports', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use Illuminate\Foundation\Http\Middleware\TransformsRequest;');
    expect($source)->toContain('use Override;');
});

test('middleware has transform method with Override attribute', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('#[Override]');
    expect($source)->toContain('protected function transform($key, $value)');
});

test('middleware handles true string conversion', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$value === \'true\'');
    expect($source)->toContain('return true;');
});

test('middleware handles uppercase TRUE string conversion', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$value === \'TRUE\'');
    expect($source)->toContain('return true;');
});

test('middleware handles false string conversion', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$value === \'false\'');
    expect($source)->toContain('return false;');
});

test('middleware handles uppercase FALSE string conversion', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$value === \'FALSE\'');
    expect($source)->toContain('return false;');
});

test('middleware returns original value for other strings', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('return $value;');
});

test('middleware has proper conditional logic', function (): void {
    $reflection = new ReflectionClass(ConvertStringToBoolean::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('if ($value === \'true\' || $value === \'TRUE\')');
    expect($source)->toContain('if ($value === \'false\' || $value === \'FALSE\')');
});
