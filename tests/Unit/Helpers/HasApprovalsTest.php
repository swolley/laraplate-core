<?php

declare(strict_types=1);

use Approval\Models\Modification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\HasApprovalsStubModel;


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

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('can')->with('approve.has_approvals_stub')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    $model = new HasApprovalsStubModel;
    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'change']))->toBeTrue();
});

it('does not require approval when modifications are empty', function (): void {
    $originalApp = $this->app;
    $app = Mockery::mock($this->app)->makePartial();
    $app->shouldReceive('runningInConsole')->andReturn(false);
    $this->app->instance('app', $app);

    try {
        Auth::shouldReceive('user')->andReturn(null);

        $model = new HasApprovalsStubModel;
        $method = new ReflectionMethod($model, 'requiresApprovalWhen');
        $method->setAccessible(true);

        expect($method->invoke($model, []))->toBeFalse();
    } finally {
        $this->app->instance('app', $originalApp);
    }
});

it('does not require approval when user is admin', function (): void {
    $originalApp = $this->app;
    $app = Mockery::mock($this->app)->makePartial();
    $app->shouldReceive('runningInConsole')->andReturn(false);
    $this->app->instance('app', $app);

    try {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAdmin')->andReturn(true);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        $model = new HasApprovalsStubModel;
        $method = new ReflectionMethod($model, 'requiresApprovalWhen');
        $method->setAccessible(true);

        expect($method->invoke($model, ['name' => 'change']))->toBeFalse();
    } finally {
        $this->app->instance('app', $originalApp);
    }
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
