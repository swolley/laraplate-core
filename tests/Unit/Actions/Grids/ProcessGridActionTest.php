<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Modules\Core\Actions\Grids\ProcessGridAction;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('processes grid with resolvers', function (): void {
    Mockery::mock('alias:Modules\Core\Helpers\PermissionChecker')
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

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toBe(['ok' => true]);
});
