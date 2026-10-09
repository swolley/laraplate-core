<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Approval;
use Modules\Core\Models\License;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\Approvals\HiddenWhilePendingDeletionModel;
use Modules\Core\Tests\Stubs\HasApprovalsStubModel;

/*
 * The model events and scopes that ask whether the acting user holds a permission ask it as the CRUD does: on the
 * request's guard and within the abilities of the token that authenticated it. A token narrows what its owner's
 * roles grant there too: an approve permission the token does not carry gives no approval credit and no sight of
 * records pending deletion, and a select permission it does not carry reads no record.
 */

beforeEach(function (): void {
    Schema::create('has_approvals_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
    Schema::create('approvals_soft_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->storedAs('deleted_at IS NOT NULL');
    });
});

afterEach(function (): void {
    Schema::dropIfExists('has_approvals_stub');
    Schema::dropIfExists('approvals_soft_stub');
});

/**
 * A staff member whose roles grant the given operations of the model on both guards, acting on the `api` guard
 * through a token carrying only the given abilities.
 *
 * @param  list<string>  $operations
 * @param  list<string>  $token_operations
 */
function tokenaware_act_with_token(Model $model, array $operations, array $token_operations): User
{
    $user = User::factory()->create();

    foreach (['web', 'api'] as $guard) {
        $role = Role::factory()->create(['name' => 'tokenaware_' . $guard . '_' . uniqid(), 'guard_name' => $guard]);

        foreach ($operations as $operation) {
            $role->givePermissionTo(Permission::findOrCreate(PermissionName::forModel($model, $operation), $guard));
        }

        $user->assignRole($role);
    }

    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->fresh();
    $abilities = array_map(static fn (string $operation): string => PermissionName::forModel($model, $operation), $token_operations);
    $token = $user->createToken('tokenaware', $abilities)->accessToken;

    Auth::shouldUse('api');
    Auth::guard('api')->setUser($user->withAccessToken($token));

    return $user;
}

/**
 * Writes outside the console are the ones the approval rules govern.
 */
function tokenaware_outside_console(): void
{
    $app = App::getFacadeRoot();
    $mock = Mockery::mock($app)->makePartial();
    $mock->shouldReceive('runningInConsole')->andReturn(false);
    App::swap($mock);
}

function tokenaware_requires_approval(HasApprovalsStubModel $model): bool
{
    $method = new ReflectionMethod($model, 'requiresApprovalWhen');

    return $method->invoke($model, ['name' => 'next']);
}

describe('the approval gate of a write', function (): void {
    it('gives no write-through to a token that does not carry the approve permission of its owner', function (): void {
        $model = new HasApprovalsStubModel;
        $model->setApproversRequired(1);
        tokenaware_act_with_token($model, ['update', 'approve'], ['update']);
        tokenaware_outside_console();

        expect(tokenaware_requires_approval($model))->toBeTrue();
    });

    it('gives the write-through to a token carrying the approve permission', function (): void {
        $model = new HasApprovalsStubModel;
        $model->setApproversRequired(1);
        tokenaware_act_with_token($model, ['update', 'approve'], ['update', 'approve']);
        tokenaware_outside_console();

        expect(tokenaware_requires_approval($model))->toBeFalse();
    });

    it('records no author approval credit for a token that does not carry the approve permission', function (): void {
        $model = HasApprovalsStubModel::query()->create(['name' => 'original']);
        $model->setApproversRequired(2);
        tokenaware_act_with_token($model, ['update', 'approve'], ['update']);
        tokenaware_outside_console();

        $model->name = 'changed';
        $model->save();

        $modification = Modification::query()
            ->where('modifiable_type', HasApprovalsStubModel::class)
            ->where('modifiable_id', $model->getKey())
            ->activeOnly()
            ->sole();

        expect(Approval::query()->where('modification_id', $modification->getKey())->exists())->toBeFalse();
    });
});

it('hides a record pending deletion from a token that does not carry the approve permission of its owner', function (): void {
    $record = HiddenWhilePendingDeletionModel::query()->create(['name' => 'kept']);
    Modification::query()->create([
        'modifiable_type' => HiddenWhilePendingDeletionModel::class,
        'modifiable_id' => $record->getKey(),
        'modifier_id' => User::factory()->create()->getKey(),
        'modifier_type' => User::class,
        'active' => true,
        'operation' => Operation::Delete,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('tokenaware-delete'),
        'modifications' => [],
    ]);

    tokenaware_act_with_token($record, ['select', 'approve'], ['select']);

    expect(HiddenWhilePendingDeletionModel::query()->whereKey($record->getKey())->exists())->toBeFalse();

    tokenaware_act_with_token($record, ['select', 'approve'], ['select', 'approve']);

    expect(HiddenWhilePendingDeletionModel::query()->whereKey($record->getKey())->exists())->toBeTrue();
});

describe('the select check of a hydrated record', function (): void {
    it('refuses a record to a token that does not carry the select permission of its owner', function (): void {
        $license = License::factory()->create();
        tokenaware_act_with_token($license, ['select'], []);

        expect(fn (): ?License => License::query()->find($license->getKey()))->toThrow(AuthorizationException::class);
    });

    it('reads the record with a token carrying the select permission', function (): void {
        $license = License::factory()->create();
        tokenaware_act_with_token($license, ['select'], ['select']);

        expect(License::query()->find($license->getKey())?->getKey())->toBe($license->getKey());
    });
});
