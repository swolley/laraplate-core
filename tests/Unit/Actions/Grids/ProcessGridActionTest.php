<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Modules\Core\Actions\Grids\ProcessGridAction;
use Modules\Core\Services\Authorization\AuthorizationService;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('processes grid with resolvers', function (): void {
    $authMock = Mockery::mock(AuthorizationService::class);
    $authMock->shouldReceive('ensurePermission')
        ->andReturn('connection.table.select');

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
        auth: $authMock,
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
