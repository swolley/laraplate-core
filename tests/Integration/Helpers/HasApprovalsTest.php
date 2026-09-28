<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\HasApprovalsStubModel;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    Schema::create('has_approvals_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('has_approvals_stub');
});

it('initializes preview visibility when preview mode is on', function (): void {
    session(['preview' => true]);

    $model = new HasApprovalsStubModel;
    $model->initializeHasApprovals();

    expect($model->getHidden())->toContain('preview')
        ->and($model->getAppends())->toContain('preview');
});

it('does not require approval when running in console', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(true);

    $model = new HasApprovalsStubModel;

    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['a' => 1]))->toBeFalse();
});

it('delegates to parent toArray when preview attribute is empty', function (): void {
    session()->forget('preview');

    $model = HasApprovalsStubModel::query()->create(['name' => 'stored']);

    expect($model->toArray()['name'] ?? null)->toBe('stored');
});

it('returns null from getPreviewAttribute when preview session is disabled', function (): void {
    session(['preview' => false]);

    $model = HasApprovalsStubModel::query()->create(['name' => 'x']);
    $method = new ReflectionMethod(HasApprovalsStubModel::class, 'getPreviewAttribute');
    $method->setAccessible(true);

    expect($method->invoke($model))->toBeNull();
});

it('requires approval when not in console and user cannot bypass approval', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $model = new HasApprovalsStubModel;
    $permission = PermissionName::forModel($model, 'approve');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with($permission)->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'change']))->toBeTrue();
});

it('does not require approval when modifications are empty', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);
    Auth::shouldReceive('user')->andReturn(null);

    $model = new HasApprovalsStubModel;
    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, []))->toBeFalse();
});

it('does not require approval when user has approve credit and N is 1', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $model = new HasApprovalsStubModel;
    $model->setApproversRequired(1);
    $permission = PermissionName::forModel($model, 'approve');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with($permission)->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'change']))->toBeFalse();
});

it('requires approval when user has approve credit but N is greater than 1', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $model = new HasApprovalsStubModel;
    $model->setApproversRequired(2);
    $permission = PermissionName::forModel($model, 'approve');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with($permission)->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'change']))->toBeTrue();
});

it('merges preview into toArray when preview data exists', function (): void {
    session(['preview' => true]);

    $model = HasApprovalsStubModel::query()->create(['name' => 'stored']);

    Modification::query()->create([
        'modifiable_type' => HasApprovalsStubModel::class,
        'modifiable_id' => $model->id,
        'md5' => md5('seed'),
        'modifications' => [
            'name' => ['modified' => 'from-modification'],
        ],
    ]);

    $fresh = $model->fresh();
    $array = $fresh->toArray();

    expect($array['name'] ?? null)->toBe('from-modification');
});

it('no longer relies on the laravel-approval traits', function (): void {
    expect(class_uses_recursive(HasApprovalsStubModel::class))
        ->not->toHaveKey('Approval\Traits\RequiresApproval')
        ->and(class_uses_recursive(User::class))
        ->not->toHaveKey('Approval\Traits\ApprovesChanges')
        ->and(class_uses_recursive(HasApprovalsStubModel::class))->toHaveKey(HasApprovals::class);
});

it('never captures what a superadmin writes, whatever the approvals required', function (): void {
    HttpContext::pretendHttpRequest();

    $superadmin = User::factory()->create();
    $superadmin->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    Auth::login($superadmin);

    $model = new HasApprovalsStubModel;
    $model->setApproversRequired(3);

    $method = new ReflectionMethod($model, 'requiresApprovalWhen');

    expect($method->invoke($model, ['name' => 'next']))->toBeFalse();
});

it('captures the write of a user who holds no approve credit', function (): void {
    // Seed the record first: once capture is on, the create would be captured too.
    $model = HasApprovalsStubModel::query()->create(['name' => 'before']);

    HttpContext::pretendHttpRequest();
    Auth::login(User::factory()->create());

    $model->name = 'after';

    expect($model->save())->toBeFalse()
        ->and($model->fresh()->name)->toBe('before')
        ->and($model->modifications()->activeOnly()->sole()->modifications)
        ->toBe(['name' => ['original' => 'before', 'modified' => 'after']]);
});
