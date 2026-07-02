<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Services\PerModelSettingResolver;
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
    App::shouldReceive('runningInConsole')->andReturn(false);
    Auth::shouldReceive('user')->andReturn(null);

    $model = new HasApprovalsStubModel;
    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, []))->toBeFalse();
});

it('does not require approval when user is admin', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    $model = new HasApprovalsStubModel;
    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'change']))->toBeFalse();
});

it('does not require approval when user is superadmin and can approve the model', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(true);
    $user->shouldReceive('can')->with('approve.has_approvals_stub')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $model = new HasApprovalsStubModel;
    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'change']))->toBeFalse();
});

it('uses declared model property for ai moderation', function (): void {
    $model = new class() extends Illuminate\Database\Eloquent\Model
    {
        use HasApprovals;

        protected bool $ai_moderation_enabled = true;
    };

    expect($model->aiModerationEnabledBySettings())->toBeTrue();
});

it('reads ai moderation from per model settings', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'ai_moderation_has_approvals_stub',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'moderation',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    expect((new HasApprovalsStubModel)->aiModerationEnabledBySettings())->toBeTrue();
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
