<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Casts\SearchGraphRequestData;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\DTOs\GraphData;
use Modules\Core\Graph\DTOs\GraphMeta;
use Modules\Core\Graph\GraphNodeSerializer;
use Modules\Core\Graph\GraphService;
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

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class), $crud, app(GraphNodeSerializer::class)))->expand($data);

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

    $result = (new GraphService($auth, $traversal, app(GraphProviderRegistryInterface::class), $crud, app(GraphNodeSerializer::class)))->search($data);

    expect($result)->toBeInstanceOf(CrudResult::class);
    expect($result->data)
        ->toHaveKey('center', null)
        ->and($result->data['nodes'])->toHaveCount(1)
        ->and($result->data['nodes'][0]['id'])->toBe('core:users:' . $user->getKey())
        ->and($result->data['edges'])->toBe([])
        ->and($result->data['graphMeta']['requestedRelations'])->toBe([])
        ->and($result->data['searchMeta']['resultCount'])->toBe(1);
});
