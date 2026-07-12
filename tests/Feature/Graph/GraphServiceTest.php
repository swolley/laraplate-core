<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Casts\SearchGraphRequestData;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\DTOs\GraphData;
use Modules\Core\Graph\DTOs\GraphEdge;
use Modules\Core\Graph\DTOs\GraphMeta;
use Modules\Core\Graph\DTOs\GraphNode;
use Modules\Core\Graph\GraphNodeSerializer;
use Modules\Core\Graph\GraphService;
use Modules\Core\Graph\GraphStatsCalculator;
use Modules\Core\Graph\GraphTraversal;
use Modules\Core\Http\Requests\ExpandGraphRequest;
use Modules\Core\Http\Requests\SearchGraphRequest;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
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

    $crud = Mockery::mock(CrudService::class);

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class), $crud, app(GraphNodeSerializer::class), app(GraphStatsCalculator::class)))->expand($data);

    expect($result)->toBeInstanceOf(CrudResult::class);
    expect($result->data)->toHaveKey('center', 'core:users:' . $user->getKey());
});

it('builds a graph from crud search results without graph relations', function (): void {
    $user = User::factory()->create(['name' => 'Alice']);

    $request = SearchGraphRequest::create('/graph/search/Core/users', 'GET', [
        'module' => 'Core',
        'entity' => 'users',
        'qs' => 'alice',
    ]);

    $data = new SearchGraphRequestData($request, 'users', [
        'qs' => 'alice',
        'relations' => [],
    ], 'id', 'Core');

    $auth = Mockery::mock(AuthorizationService::class);
    $traversal = Mockery::mock(GraphTraversal::class);
    $traversal->shouldNotReceive('expand');

    $crud = Mockery::mock(CrudService::class);
    $crud->shouldReceive('search')->once()->with($data)->andReturn(new CrudResult(collect([$user])));

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class), $crud, app(GraphNodeSerializer::class), app(GraphStatsCalculator::class)))->search($data);

    expect($result)->toBeInstanceOf(CrudResult::class);
    expect($result->data)
        ->toHaveKey('center', null)
        ->and($result->data['nodes'])->toHaveCount(1)
        ->and($result->data['nodes'][0]['id'])->toBe('core:users:' . $user->getKey())
        ->and($result->data['edges'])->toBe([])
        ->and($result->data['graphMeta']['requestedRelations'])->toBe([])
        ->and($result->data['searchMeta']['resultCount'])->toBe(1);
});

it('aggregates expanded search graphs and counts cross result deduplicated nodes', function (): void {
    $first = User::factory()->create(['name' => 'Alice One']);
    $second = User::factory()->create(['name' => 'Alice Two']);

    $request = SearchGraphRequest::create('/graph/search/Core/users', 'GET', [
        'module' => 'Core',
        'entity' => 'users',
        'qs' => 'alice',
        'relations' => ['roles'],
    ]);

    $data = new SearchGraphRequestData($request, 'users', [
        'qs' => 'alice',
        'relations' => ['roles'],
    ], 'id', 'Core');

    $sharedNode = new GraphNode(
        id: 'core:roles:1',
        module: 'core',
        entity: 'roles',
        key: 1,
        label: 'Editor',
    );

    $auth = Mockery::mock(AuthorizationService::class);
    $crud = Mockery::mock(CrudService::class);
    $crud->shouldReceive('search')->once()->with($data)->andReturn(new CrudResult(collect([$first, $second])));

    $traversal = Mockery::mock(GraphTraversal::class);
    $traversal->shouldReceive('expand')->once()->with(
        $first,
        ['roles'],
        1,
        100,
        25,
        'summary',
        $request,
    )->andReturn(new GraphData(
        center: 'core:users:' . $first->getKey(),
        nodes: [
            new GraphNode('core:users:' . $first->getKey(), 'core', 'users', $first->getKey(), 'Alice One'),
            $sharedNode,
        ],
        edges: [
            new GraphEdge('edge:one', 'core:users:' . $first->getKey(), 'core:roles:1', 'roles'),
        ],
        graphMeta: new GraphMeta(depth: 1, requestedRelations: ['roles']),
    ));

    $traversal->shouldReceive('expand')->once()->with(
        $second,
        ['roles'],
        1,
        100,
        25,
        'summary',
        $request,
    )->andReturn(new GraphData(
        center: 'core:users:' . $second->getKey(),
        nodes: [
            new GraphNode('core:users:' . $second->getKey(), 'core', 'users', $second->getKey(), 'Alice Two'),
            $sharedNode,
        ],
        edges: [
            new GraphEdge('edge:two', 'core:users:' . $second->getKey(), 'core:roles:1', 'roles'),
        ],
        graphMeta: new GraphMeta(depth: 1, requestedRelations: ['roles'], truncated: true, truncatedBy: ['relation_limit'], filteredByAcl: true),
    ));

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class), $crud, app(GraphNodeSerializer::class), app(GraphStatsCalculator::class)))->search($data);

    expect($result->data['nodes'])->toHaveCount(3)
        ->and($result->data['edges'])->toHaveCount(2)
        ->and($result->data['graphMeta']['requestedRelations'])->toBe(['roles'])
        ->and($result->data['graphMeta']['truncated'])->toBeTrue()
        ->and($result->data['graphMeta']['truncatedBy'])->toBe(['relation_limit'])
        ->and($result->data['graphMeta']['filteredByAcl'])->toBeTrue()
        ->and($result->data['graphMeta']['deduplicatedNodeCount'])->toBe(1)
        ->and($result->data['searchMeta']['resultCount'])->toBe(2);
});

it('returns crud search errors without building a graph', function (): void {
    $request = SearchGraphRequest::create('/graph/search/Core/users', 'GET', [
        'module' => 'Core',
        'entity' => 'users',
        'qs' => 'alice',
    ]);

    $data = new SearchGraphRequestData($request, 'users', [
        'qs' => 'alice',
        'relations' => [],
    ], 'id', 'Core');

    $auth = Mockery::mock(AuthorizationService::class);
    $traversal = Mockery::mock(GraphTraversal::class);
    $traversal->shouldNotReceive('expand');

    $crud = Mockery::mock(CrudService::class);
    $crud->shouldReceive('search')->once()->with($data)->andReturn(new CrudResult(
        data: null,
        error: 'Search failed',
        statusCode: 400,
    ));

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class), $crud, app(GraphNodeSerializer::class), app(GraphStatsCalculator::class)))->search($data);

    expect($result->data)->toBeNull()
        ->and($result->error)->toBe('Search failed')
        ->and($result->statusCode)->toBe(400);
});

it('builds stats from the same graph data used by expand', function (): void {
    $user = User::factory()->create(['name' => 'Center']);

    $request = ExpandGraphRequest::create('/graph/stats/Core/users/' . $user->getKey(), 'GET', [
        'module' => 'Core',
        'entity' => 'users',
        'id' => $user->getKey(),
        'relations' => ['roles'],
    ]);

    $data = new ExpandGraphRequestData($request, 'users', [
        'id' => $user->getKey(),
        'relations' => ['roles'],
    ], 'id', 'Core');

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('ensurePermission')->once()->andReturn('default.core_users.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->once();

    $traversal = Mockery::mock(GraphTraversal::class);
    $traversal->shouldReceive('expand')->once()->andReturn(new GraphData(
        center: 'core:users:' . $user->getKey(),
        nodes: [
            new GraphNode('core:users:' . $user->getKey(), 'core', 'users', $user->getKey(), 'Center'),
            new GraphNode('core:roles:1', 'core', 'roles', 1, 'Editor'),
        ],
        edges: [
            new GraphEdge('edge:roles', 'core:users:' . $user->getKey(), 'core:roles:1', 'roles', 'assigned_role'),
        ],
        graphMeta: new GraphMeta(depth: 1, requestedRelations: ['roles'], truncated: true, truncatedBy: ['relation_limit']),
    ));

    $crud = Mockery::mock(CrudService::class);

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class), $crud, app(GraphNodeSerializer::class), app(GraphStatsCalculator::class)))->stats($data);

    expect($result)->toBeInstanceOf(CrudResult::class)
        ->and($result->data['center'])->toBe('core:users:' . $user->getKey())
        ->and($result->data['stats'])->toBe([
            'totalNodes' => 2,
            'totalEdges' => 1,
            'nodesByModule' => ['core' => 2],
            'nodesByEntity' => [
                'core:roles' => 1,
                'core:users' => 1,
            ],
            'edgesByRelation' => ['roles' => 1],
            'edgesByType' => ['assigned_role' => 1],
        ])
        ->and($result->data['graphMeta']['truncated'])->toBeTrue()
        ->and($result->data['graphMeta']['truncatedBy'])->toBe(['relation_limit']);
});
