<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\DTOs\GraphData;
use Modules\Core\Graph\DTOs\GraphMeta;
use Modules\Core\Graph\GraphService;
use Modules\Core\Graph\GraphTraversal;
use Modules\Core\Http\Requests\ExpandGraphRequest;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\DTOs\CrudResult;

uses(RefreshDatabase::class);

it('loads the center record through detail semantics and returns a crud result', function (): void {
    $user = User::factory()->create(['name' => 'Center']);

    $request = ExpandGraphRequest::create('/graph/Core/users/' . $user->getKey(), 'GET', [
        'module' => 'Core',
        'entity' => 'users',
        'id' => $user->getKey(),
    ]);

    $data = new ExpandGraphRequestData($request, 'users', [
        'id' => $user->getKey(),
        'relations' => [],
    ], 'id', 'Core');

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('ensurePermission')->once()->andReturn('default.core_users.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->once();

    $traversal = Mockery::mock(GraphTraversal::class);
    $traversal->shouldReceive('expand')->once()->andReturn(
        new GraphData(
            center: 'core:users:' . $user->getKey(),
            nodes: [],
            edges: [],
            graphMeta: new GraphMeta(depth: 1, requestedRelations: []),
        )
    );

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class)))->expand($data);

    expect($result)->toBeInstanceOf(CrudResult::class);
    expect($result->data)->toHaveKey('center', 'core:users:' . $user->getKey());
});
