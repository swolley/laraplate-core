<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Events\ModificationApproved;
use Modules\Core\Events\ModificationRejected;
use Modules\Core\Events\ModificationWithdrawn;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ModificationVoteService;
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
    SoftDeletableApprovalModel::$writable = [];
    $this->record = SoftDeletableApprovalModel::query()->create(['name' => 'kept']);
    HttpContext::pretendHttpRequest();
    $this->author = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->approver->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
});

afterEach(fn () => Schema::dropIfExists('approvals_soft_stub'));

it('fires one event per decision', function (): void {
    Event::fake([ModificationApproved::class, ModificationRejected::class, ModificationWithdrawn::class]);
    $service = resolve(ModificationVoteService::class);
    Auth::login($this->author);

    $this->record->update(['name' => 'first']);
    $service->cast($this->approver, $this->record->pendingModification(), true);

    $this->record->update(['name' => 'second']);
    $service->cast($this->approver, $this->record->pendingModification(), false);

    $this->record->update(['name' => 'third']);
    $service->withdraw($this->author, $this->record->pendingModification());

    Event::assertDispatchedTimes(ModificationApproved::class, 1);
    Event::assertDispatchedTimes(ModificationRejected::class, 1);
    Event::assertDispatchedTimes(ModificationWithdrawn::class, 1);
});
