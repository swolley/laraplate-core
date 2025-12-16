<?php

declare(strict_types=1);

use Modules\Core\Actions\Grids\GetGridConfigsAction;
use Tests\TestCase;

uses(TestCase::class);

it('returns configs for models', function (): void {
    $action = new GetGridConfigsAction(
        modelsProvider: fn () => ['ModelOne'],
        gridResolver: fn () => ['config' => true],
    );

    $result = $action(request(), null);

    expect($result)->toBe(['ModelOne' => ['config' => true]]);
});

it('filters single entity', function (): void {
    $action = new GetGridConfigsAction(
        modelsProvider: fn () => ['ModelOne'],
        gridResolver: fn ($model, $entity) => $entity === 'ModelOne' ? ['only' => true] : null,
    );

    $result = $action(request(), 'ModelOne');

    expect($result)->toBe(['only' => true]);
});
