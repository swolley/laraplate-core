<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\HasApprovalsStubModel;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Schema::create('has_approvals_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('has_approvals_stub');
    Modification::query()->where('modifiable_type', HasApprovalsStubModel::class)->delete();
});

/**
 * @return array{0: User, 1: string}
 */
function quorumApproverUser(HasApprovalsStubModel $model): array
{
    $user = User::factory()->create();
    $permission_name = PermissionName::forModel($model, 'approve');
    Permission::findOrCreate($permission_name, 'web');
    $user->givePermissionTo($permission_name);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    return [$user, $permission_name];
}

it('write-through when writer has approve credit and N is 1', function (): void {
    $app = App::getFacadeRoot();
    $mock = Mockery::mock($app)->makePartial();
    $mock->shouldReceive('runningInConsole')->andReturn(false);
    App::swap($mock);

    $model = new HasApprovalsStubModel;
    $model->setApproversRequired(1);
    [$user] = quorumApproverUser($model);
    Auth::login($user);

    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'next']))->toBeFalse();
});

it('creates modification plus author approve credit when N is greater than 1', function (): void {
    $model = HasApprovalsStubModel::query()->create(['name' => 'original']);
    $model->setApproversRequired(2);
    [$user] = quorumApproverUser($model);
    Auth::login($user);

    $app = App::getFacadeRoot();
    $mock = Mockery::mock($app)->makePartial();
    $mock->shouldReceive('runningInConsole')->andReturn(false);
    App::swap($mock);

    $model->name = 'changed';
    $model->save();

    $modification = Modification::query()
        ->where('modifiable_type', HasApprovalsStubModel::class)
        ->where('modifiable_id', $model->getKey())
        ->activeOnly()
        ->sole();

    expect($modification->approvers_required)->toBe(2)
        ->and(HasApprovalsStubModel::query()->whereKey($model->getKey())->value('name'))->toBe('original');

    $approval = Approval::query()->where('modification_id', $modification->getKey())->sole();

    expect($approval->approver_id)->toBe($user->getKey())
        ->and($approval->meta)->toMatchArray(['source' => 'author_approve_permission'])
        ->and((int) $modification->fresh()->approversRemaining)->toBe(1);
});

it('rejects a further approve vote by the modification author', function (): void {
    $user = User::factory()->create();
    $model = HasApprovalsStubModel::query()->create(['name' => 'x']);

    $permission_name = PermissionName::forModel($model, 'approve');
    Permission::findOrCreate($permission_name, 'web');
    $user->givePermissionTo($permission_name);

    $modification = Modification::query()->create([
        'modifiable_type' => HasApprovalsStubModel::class,
        'modifiable_id' => $model->getKey(),
        'modifier_id' => $user->getKey(),
        'modifier_type' => $user::class,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 2,
        'disapprovers_required' => 1,
        'md5' => md5('self-vote'),
        'modifications' => ['name' => ['original' => 'x', 'modified' => 'y']],
    ]);
    $modification->setRelation('modifiable', $model);

    expect($user->isAuthorizedToCastApprovalVote($modification, true))->toBeFalse()
        ->and($user->isAuthorizedToCastApprovalVote($modification, false))->toBeFalse();
});

it('still requires approval when writer lacks approve credit even if admin role helpers would have bypassed before', function (): void {
    $app = App::getFacadeRoot();
    $mock = Mockery::mock($app)->makePartial();
    $mock->shouldReceive('runningInConsole')->andReturn(false);
    App::swap($mock);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->andReturn(false);
    Auth::shouldReceive('user')->andReturn($user);

    $model = new HasApprovalsStubModel;
    $model->setApproversRequired(1);
    $method = new ReflectionMethod($model, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($model, ['name' => 'change']))->toBeTrue();
});
