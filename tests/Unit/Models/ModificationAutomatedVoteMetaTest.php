<?php

declare(strict_types=1);

use Modules\CMS\Models\Comment;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

it('returns latest automated vote meta from approval or disapproval', function (): void {
    $user = User::factory()->create();

    $modification = Modification::query()->create([
        'modifiable_type' => Comment::class,
        'modifiable_id' => null,
        'modifier_id' => $user->id,
        'modifier_type' => User::class,
        'active' => true,
        'is_update' => false,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('meta-test'),
        'modifications' => ['body' => ['original' => null, 'modified' => 'x']],
    ]);

    Approval::query()->create([
        'modification_id' => $modification->id,
        'approver_id' => $user->id,
        'approver_type' => User::class,
        'reason' => 'older',
        'meta' => ['source' => 'ai', 'status' => 'auto_approved'],
    ]);

    Disapproval::query()->create([
        'modification_id' => $modification->id,
        'disapprover_id' => $user->id,
        'disapprover_type' => User::class,
        'reason' => 'newer',
        'meta' => ['source' => 'ai', 'status' => 'requires_human_review'],
        'created_at' => now()->addSecond(),
        'updated_at' => now()->addSecond(),
    ]);

    expect($modification->latestAutomatedVoteMeta())->toMatchArray([
        'source' => 'ai',
        'status' => 'requires_human_review',
    ]);
});

it('returns null when no vote has meta', function (): void {
    $user = User::factory()->create();

    $modification = Modification::query()->create([
        'modifiable_type' => Comment::class,
        'modifiable_id' => null,
        'modifier_id' => $user->id,
        'modifier_type' => User::class,
        'active' => true,
        'is_update' => false,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('no-meta'),
        'modifications' => [],
    ]);

    expect($modification->latestAutomatedVoteMeta())->toBeNull();
});
