<?php

declare(strict_types=1);

use Modules\Core\Actions\Grids\GetGridConfigsAction;
use Tests\TestCase;

final class GetGridConfigsActionTest extends TestCase
{
    public function test_returns_configs_for_models(): void
    {
        $action = new GetGridConfigsAction(
            modelsProvider: fn () => ['ModelOne'],
            gridResolver: fn () => ['config' => true],
        );

        $result = $action(request(), null);

        $this->assertSame(['ModelOne' => ['config' => true]], $result);
    }

    public function test_filters_single_entity(): void
    {
        $action = new GetGridConfigsAction(
            modelsProvider: fn () => ['ModelOne'],
            gridResolver: fn ($model, $entity) => $entity === 'ModelOne' ? ['only' => true] : null,
        );

        $result = $action(request(), 'ModelOne');

        $this->assertSame(['only' => true], $result);
    }
}

