<?php

declare(strict_types=1);

use Modules\CMS\Models\Comment;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

it('exposes active but keeps modifier and quorum columns hidden', function (): void {
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

    // active must reach the payload so the UI can flag pending records.
    expect($array)->toHaveKey('active')
        ->and($array['active'])->toBeTruthy()
        // modifier identity and quorum counts stay hidden for now.
        ->and($array)->not->toHaveKey('modifier_id')
        ->and($array)->not->toHaveKey('approvers_required');
});
