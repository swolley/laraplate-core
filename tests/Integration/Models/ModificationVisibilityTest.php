<?php

declare(strict_types=1);

use Modules\CMS\Models\Comment;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

it('exposes active and modifier identity but keeps quorum columns hidden', function (): void {
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
        'md5' => md5('visibility-test'),
        'modifications' => ['body' => ['original' => null, 'modified' => 'x']],
    ]);

    $array = $modification->fresh()->toArray();

    // active + modifier identity must reach the payload so the UI can flag pending
    // records and tell whether the viewer authored the pending modification.
    expect($array)->toHaveKey('active')
        ->and($array['active'])->toBeTruthy()
        ->and($array)->toHaveKey('modifier_id')
        ->and((int) $array['modifier_id'])->toBe($user->id)
        // quorum counts stay hidden.
        ->and($array)->not->toHaveKey('approvers_required')
        ->and($array)->not->toHaveKey('disapprovers_required');
});
