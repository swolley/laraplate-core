<?php

declare(strict_types=1);

use Modules\Core\Listeners\TrackUserLogout;

test('listener has correct class structure', function (): void {
    $reflection = new ReflectionClass(TrackUserLogout::class);

    expect($reflection->getName())->toBe('Modules\Core\Listeners\TrackUserLogout');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('listener handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(TrackUserLogout::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('void');
    expect($reflection->isPublic())->toBeTrue();
});

test('listener uses correct imports', function (): void {
    $reflection = new ReflectionClass(TrackUserLogout::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use Illuminate\Auth\Events\Logout;');
});

test('listener handle method processes logout event', function (): void {
    $reflection = new ReflectionClass(TrackUserLogout::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('public function handle(Logout $event): void');
    expect($source)->toContain('$user = $event->user;');
});

test('listener handles user license deletion', function (): void {
    $reflection = new ReflectionClass(TrackUserLogout::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$user->license?->delete();');
});

test('listener has commented session handling', function (): void {
    $reflection = new ReflectionClass(TrackUserLogout::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('// $sessionId = session()->getId();');
    expect($source)->toContain('// Remove session from the database');
});

test('listener has proper type annotation', function (): void {
    $reflection = new ReflectionClass(TrackUserLogout::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('/** @var Model $user */');
});
