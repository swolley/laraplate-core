<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\Approvals\SoftDeletableApprovalModel;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    Schema::create('approvals_soft_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->storedAs('deleted_at IS NOT NULL');
    });
    SoftDeletableApprovalModel::$operations = null;
    SoftDeletableApprovalModel::$approvers = null;
    $this->record = SoftDeletableApprovalModel::query()->create(['name' => 'kept']);
    HttpContext::pretendHttpRequest();
    Auth::login(User::factory()->create());
});

afterEach(fn () => Schema::dropIfExists('approvals_soft_stub'));

it('captures a soft delete instead of running it', function (): void {
    expect($this->record->delete())->toBeFalse()
        ->and($this->record->fresh()->trashed())->toBeFalse()
        ->and($this->record->pendingModification()?->operation)->toBe(Operation::Delete)
        ->and($this->record->pendingModification()?->modifications)->toBe([]);
});

it('captures a force delete as force_delete', function (): void {
    expect($this->record->forceDelete())->toBeFalse()
        ->and(SoftDeletableApprovalModel::query()->whereKey($this->record->id)->exists())->toBeTrue()
        ->and($this->record->pendingModification()?->operation)->toBe(Operation::ForceDelete);
});

it('captures a restore of a trashed record', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    Auth::login($superadmin);
    $this->record->delete();
    Auth::login(User::factory()->create());

    $trashed = SoftDeletableApprovalModel::withTrashed()->find($this->record->id);

    expect($trashed->restore())->toBeFalse()
        ->and($trashed->fresh()->trashed())->toBeTrue()
        ->and($trashed->pendingModification()?->operation)->toBe(Operation::Restore);
});

it('keeps a single pending request per deletion', function (): void {
    $this->record->delete();
    $this->record->delete();

    expect($this->record->modifications()->activeOnly()->count())->toBe(1);
});

it('lets an operation the model does not cover run directly', function (): void {
    SoftDeletableApprovalModel::$operations = [Operation::Update];

    expect($this->record->delete())->toBeTrue()
        ->and(SoftDeletableApprovalModel::withTrashed()->find($this->record->id)->trashed())->toBeTrue();
});

it('answers in advance whether an operation needs approval', function (): void {
    expect($this->record->wouldRequireApproval(Operation::Delete))->toBeTrue();

    SoftDeletableApprovalModel::$operations = [Operation::Update];

    expect($this->record->wouldRequireApproval(Operation::Delete))->toBeFalse();
});
