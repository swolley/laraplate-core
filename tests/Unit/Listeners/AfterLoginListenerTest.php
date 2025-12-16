<?php

declare(strict_types=1);

use Modules\Core\Listeners\AfterLoginListener;

it('listener has correct class structure', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);

    expect($reflection->getName())->toBe('Modules\Core\Listeners\AfterLoginListener');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
    expect($reflection->hasMethod('checkUserLicense'))->toBeTrue();
});

it('listener handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(AfterLoginListener::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('void');
    expect($reflection->isPublic())->toBeTrue();
});

it('listener checkUserLicense method has correct signature', function (): void {
    $reflection = new ReflectionMethod(AfterLoginListener::class, 'checkUserLicense');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('void');
    expect($reflection->isStatic())->toBeTrue();
    expect($reflection->isPublic())->toBeTrue();
});

it('listener uses correct imports', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use Illuminate\Auth\Events\Login;');
    expect($source)->toContain('use Illuminate\Contracts\Auth\Authenticatable;');
    expect($source)->toContain('use Illuminate\Support\Facades\Auth;');
    expect($source)->toContain('use Illuminate\Support\Facades\Date;');
    expect($source)->toContain('use Illuminate\Support\Facades\Log;');
    expect($source)->toContain('use Illuminate\Validation\UnauthorizedException;');
    expect($source)->toContain('use Lab404\Impersonate\Impersonate;');
    expect($source)->toContain('use Modules\Core\Models\License;');
    expect($source)->toContain('use Modules\Core\Models\User;');
    expect($source)->toContain('use RuntimeException;');
});

it('listener handle method processes login event', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('public function handle(Login $login): void');
    expect($source)->toContain('$user = $login->user;');
});

it('listener checks for impersonate trait', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('class_uses_trait($user, Impersonate::class)');
});

it('listener calls checkUserLicense method', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('self::checkUserLicense($user);');
});

it('listener updates last login timestamp', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$user->update([\'last_login_at\' => Date::now()]);');
});

it('listener handles unlocked users', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('if ($user->isUnlocked())');
    expect($source)->toContain('Auth::logoutOtherDevices($user->password);');
});

it('listener logs user login', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Log::info(\'{username} logged in\'');
    expect($source)->toContain('[\'username\' => $user->username]');
});

it('listener handles impersonation', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$user->isImpersonated()');
    expect($source)->toContain('$user->getImpersonator()');
    expect($source)->toContain('Log::info(\'{impersonator} is impersonating {impersonated}\'');
});

it('listener checkUserLicense method handles license logic', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('config(\'auth.enable_user_licenses\')');
    expect($source)->toContain('$user->isGuest()');
    expect($source)->toContain('$user->isSuperadmin()');
    expect($source)->toContain('$user->license_id === null');
});

it('listener checkUserLicense method queries available licenses', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('License::query()->whereDoesntHave(\'user\')->get()');
    expect($source)->toContain('throw_if($available_licenses->isEmpty()');
    expect($source)->toContain('$user->license()->associate($available_licenses->first())');
});

it('listener has proper exception handling', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('@throws RuntimeException');
    expect($source)->toContain('@throws UnauthorizedException');
    expect($source)->toContain('UnauthorizedException::class');
});
