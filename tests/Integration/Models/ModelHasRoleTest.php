<?php

declare(strict_types=1);

use Modules\Core\Models\Pivot\ModelHasRole;

it('defaults team id when teams permission mode is enabled', function (): void {
    config(['permission.teams' => true]);

    $pivot = new ModelHasRole();

    expect($pivot->getAttributes()['team_id'] ?? null)->toBe(1);
});

it('does not inject team id when teams permission mode is disabled', function (): void {
    config(['permission.teams' => false]);

    $pivot = new ModelHasRole();

    expect($pivot->getAttributes())->not->toHaveKey('team_id');
});
