<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Core\Events\ModificationRequiresModeration;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

it('emits ModificationRequiresModeration when an active modification is created', function (): void {
    Event::fake([ModificationRequiresModeration::class]);

    $user = User::factory()->create();

    $modification = Modification::query()->create([
        'modifiable_type' => User::class,
        'modifiable_id' => null,
        'modifier_id' => $user->id,
        'modifier_type' => User::class,
        'active' => true,
        'is_update' => false,
        'md5' => md5('emit-test'),
        'modifications' => ['name' => ['original' => 'a', 'modified' => 'b']],
    ]);

    Event::assertDispatched(ModificationRequiresModeration::class, function (ModificationRequiresModeration $event) use ($modification): bool {
        return $event->modification->is($modification);
    });
});
