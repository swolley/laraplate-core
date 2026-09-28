<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

it('stands alone without the package base classes', function (): void {
    expect(get_parent_class(Modification::class))->toBe(Model::class)
        ->and(get_parent_class(Approval::class))->toBe(Model::class)
        ->and(get_parent_class(Disapproval::class))->toBe(Model::class);
});

it('counts the approvals and disapprovals still needed', function (): void {
    $user = User::factory()->create();
    $modification = Modification::query()->create([
        'modifiable_type' => User::class,
        'modifiable_id' => $user->id,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 2,
        'disapprovers_required' => 1,
        'md5' => md5('counts'),
        'modifications' => ['name' => ['original' => 'a', 'modified' => 'b']],
    ]);

    Approval::query()->create([
        'approver_id' => $user->id,
        'approver_type' => User::class,
        'modification_id' => $modification->id,
    ]);

    expect($modification->approversRemaining)->toBe(1)
        ->and($modification->disapproversRemaining)->toBe(1)
        ->and(Modification::query()->activeOnly()->whereKey($modification->id)->exists())->toBeTrue()
        ->and($modification->approvals()->sole()->modification->is($modification))->toBeTrue();
});

it('casts active to a boolean and the diff to an array', function (): void {
    $user = User::factory()->create();
    $modification = Modification::query()->create([
        'modifiable_type' => User::class,
        'modifiable_id' => $user->id,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('casts'),
        'modifications' => ['name' => ['original' => 'a', 'modified' => 'b']],
    ]);

    $fresh = $modification->fresh();

    expect($fresh->active)->toBeTrue()
        ->and($fresh->modifications)->toBe(['name' => ['original' => 'a', 'modified' => 'b']])
        ->and(Modification::query()->inactiveOnly()->whereKey($fresh->id)->exists())->toBeFalse();
});

it('reads the modifier and the modifiable through their morph relations', function (): void {
    $user = User::factory()->create();
    $modification = Modification::query()->create([
        'modifiable_type' => User::class,
        'modifiable_id' => $user->id,
        'modifier_type' => User::class,
        'modifier_id' => $user->id,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('morphs'),
        'modifications' => [],
    ]);

    Disapproval::query()->create([
        'disapprover_id' => $user->id,
        'disapprover_type' => User::class,
        'modification_id' => $modification->id,
    ]);

    expect($modification->modifiable->is($user))->toBeTrue()
        ->and($modification->modifier->is($user))->toBeTrue()
        ->and($modification->disapprovals()->sole()->disapprover->is($user))->toBeTrue()
        ->and($modification->disapproversRemaining)->toBe(0);
});
