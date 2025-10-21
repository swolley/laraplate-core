<?php

declare(strict_types=1);

use Modules\Core\Models\License;
use Modules\Core\Models\User;

test('user authentication workflow structure', function (): void {
    // 1. Test User model structure
    $reflection = new ReflectionClass(User::class);
    expect($reflection->hasMethod('license'))->toBeTrue();
    expect($reflection->hasMethod('isSuperAdmin'))->toBeTrue();
    expect($reflection->hasMethod('canImpersonate'))->toBeTrue();
    
    // 2. Test License model structure
    $reflection = new ReflectionClass(License::class);
    expect($reflection->hasMethod('user'))->toBeTrue();
    
    // 3. Test relationship methods
    $userReflection = new ReflectionClass(User::class);
    $source = file_get_contents($userReflection->getFileName());
    
    expect($source)->toContain('public function license()');
    expect($source)->toContain('return $this->belongsTo(License::class)');
});

test('user registration workflow structure', function (): void {
    // 1. Test User model fillable attributes
    $reflection = new ReflectionClass(User::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('name');
    expect($source)->toContain('email');
    expect($source)->toContain('username');
    expect($source)->toContain('password');
    
    // 2. Test User model casts method
    expect($source)->toContain('protected function casts()');
    expect($source)->toContain('email_verified_at');
    expect($source)->toContain('password');
    
    // 3. Test User model traits
    expect($source)->toContain('HasRoles');
    expect($source)->toContain('TwoFactorAuthenticatable');
});

test('user impersonation workflow structure', function (): void {
    // 1. Test User model impersonation methods
    $reflection = new ReflectionClass(User::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('public function canImpersonate()');
    expect($source)->toContain('Permission::findByName');
    expect($source)->toContain('impersonate');
    
    // 2. Test User model impersonation traits
    expect($source)->toContain('TwoFactorAuthenticatable');
    
    // 3. Test User model impersonation logic
    expect($source)->toContain('try {');
    expect($source)->toContain('} catch (\\Spatie\\Permission\\Exceptions\\PermissionDoesNotExist $e)');
    expect($source)->toContain('return false;');
});

test('user license management workflow structure', function (): void {
    // 1. Test License model structure
    $reflection = new ReflectionClass(License::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('public function user()');
    expect($source)->toContain('return $this->hasOne(User::class)');
    
    // 2. Test License model fillable attributes
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('valid_from');
    expect($source)->toContain('valid_to');
    
    // 3. Test License model casts
    expect($source)->toContain('protected $casts');
    expect($source)->toContain('valid_from');
    expect($source)->toContain('valid_to');
});

test('user role and permission workflow structure', function (): void {
    // 1. Test User model role methods
    $reflection = new ReflectionClass(User::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('HasRoles');
    
    // 2. Test User model role logic
    expect($source)->toContain('public function isSuperAdmin()');
    expect($source)->toContain('config(\'permission.roles.superadmin\')');
    
    // 3. Test User model permission logic
    expect($source)->toContain('public function canImpersonate()');
    expect($source)->toContain('Permission::findByName');
});

test('user profile update workflow structure', function (): void {
    // 1. Test User model update methods
    $reflection = new ReflectionClass(User::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('name');
    expect($source)->toContain('email');
    expect($source)->toContain('username');
    
    // 2. Test User model password handling
    expect($source)->toContain('password');
    expect($source)->toContain('protected $hidden');
    expect($source)->toContain('password');
    
    // 3. Test User model timestamps
    expect($source)->toContain('protected function casts()');
    expect($source)->toContain('created_at');
});

test('authentication middleware workflow structure', function (): void {
    // 1. Test authentication middleware
    $reflection = new ReflectionClass(\Modules\Core\Http\Middleware\EnsureIsSuperAdmin::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('Auth::check()');
    expect($source)->toContain('Auth::user()');
    expect($source)->toContain('isSuperAdmin()');
    
    // 2. Test CRUD API middleware
    $reflection = new ReflectionClass(\Modules\Core\Http\Middleware\EnsureCrudApiAreEnabled::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('config(\'core.expose_crud_api\')');
    expect($source)->toContain('abort_unless');
});

test('user authentication routes structure', function (): void {
    // 1. Test authentication routes exist in route files
    $authRoutesFile = __DIR__ . '/../../../routes/auth.php';
    expect(file_exists($authRoutesFile))->toBeTrue();
    
    $authRoutesContent = file_get_contents($authRoutesFile);
    expect($authRoutesContent)->toContain('userInfo');
    expect($authRoutesContent)->toContain('impersonate');
    expect($authRoutesContent)->toContain('leaveImpersonate');
    
    // 2. Test route middleware
    expect($authRoutesContent)->toContain('withoutMiddleware(\'auth\')');
    expect($authRoutesContent)->toContain('->can(\'impersonate\')');
});
