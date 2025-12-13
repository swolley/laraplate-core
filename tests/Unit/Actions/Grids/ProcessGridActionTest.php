<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Modules\Core\Actions\Grids\ProcessGridAction;
use Modules\Core\Grids\Requests\GridRequest;
use Tests\TestCase;

final class ProcessGridActionTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_processes_grid_with_resolvers(): void
    {
        \Mockery::mock('alias:Modules\Core\Helpers\PermissionChecker')
            ->shouldReceive('ensurePermissions')
            ->andReturnTrue();

        $request = new class
        {
            public function parsed(): array
            {
                return [
                    'connection' => null,
                    'action' => (object) ['value' => 'select'],
                ];
            }
        };

        $model = new class
        {
            public function getTable(): string
            {
                return 'table';
            }

            public function getConnectionName(): string
            {
                return 'connection';
            }
        };

        $action = new ProcessGridAction(
            entityResolver: fn () => $model,
            gridFactory: fn () => new class
            {
                public function process(): JsonResponse
                {
                    return response()->json(['ok' => true]);
                }
            },
        );

        $response = $action($request, 'entity');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }
}

