<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\GraphEntityResolver;
use Modules\Core\Graph\GraphNodeSerializer;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Graph\GraphProviderRuleEnforcer;
use Modules\Core\Graph\GraphRelationInspector;
use Modules\Core\Graph\GraphTraversal;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Tests\Stubs\Graphs\GraphTraversalActivity;
use Modules\Core\Tests\Stubs\Graphs\GraphTraversalChild;
use Modules\Core\Tests\Stubs\Graphs\GraphTraversalParent;
use Modules\Core\Tests\Stubs\Graphs\GraphTraversalRulesProvider;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('graph_traversal_parents', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('graph_traversal_children', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('parent_id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('graph_traversal_activities', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('subject');
        $table->string('name');
        $table->timestamps();
    });
});

it('walks requested relations with deterministic nodes and edges', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child A']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child B']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->zeroOrMoreTimes();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($parent, ['children'], 1, 10, 25, 'summary', request());

    expect($data->center)->toBe('app:graph_traversal_parents:' . $parent->getKey());
    expect($data->nodes)->toHaveCount(3);
    expect($data->edges)->toHaveCount(2);
    expect($data->graphMeta->truncated)->toBeFalse();
});

it('marks relation limit truncation', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child A']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child B']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->zeroOrMoreTimes();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($parent, ['children'], 1, 10, 1, 'summary', request());

    expect($data->nodes)->toHaveCount(2);
    expect($data->graphMeta->truncated)->toBeTrue();
    expect($data->graphMeta->truncatedBy)->toContain('relation_limit');
});

it('rejects relation limits above provider relation maximum during traversal', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();

    $registry = new GraphProviderRegistry();
    $registry->register(new GraphTraversalRulesProvider(), 'app', 'graph_traversal_parents');
    $entities = new GraphEntityResolver();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer($entities, $registry),
        $entities,
        $registry,
        $auth,
        new GraphProviderRuleEnforcer($entities, $registry),
    );

    expect(fn () => $traversal->expand($parent, ['children'], 1, 10, 2, 'summary', request()))
        ->toThrow(ValidationException::class, "Relation 'children' relation_limit exceeds provider maximum.");
});

it('marks provider defaults as applied when traversal receives default relations', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->zeroOrMoreTimes();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($parent, [], 1, 10, 25, 'summary', request(), defaultRelationsApplied: true);

    expect($data->graphMeta->defaultRelationsApplied)->toBeTrue();
});

it('marks acl filtering when relation targets are removed by acl constraints', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child A']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child B']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->andReturnUsing(static function ($query): void {
        $query->where('id', -1);
    });

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($parent, ['children'], 1, 10, 25, 'summary', request());

    expect($data->nodes)->toHaveCount(1);
    expect($data->edges)->toHaveCount(0);
    expect($data->graphMeta->filteredByAcl)->toBeTrue();
});

it('deduplicates repeated nodes and marks cycles', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child A']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->zeroOrMoreTimes();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($parent, ['children.parent'], 2, 10, 25, 'summary', request());

    expect($data->nodes)->toHaveCount(2);
    expect($data->edges)->toHaveCount(2);
    expect($data->graphMeta->hasCycles)->toBeTrue();
    expect($data->graphMeta->deduplicatedNodeCount)->toBe(1);
});

it('traverses morph to relations when the concrete target is authorized', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);
    $child = GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child A']);
    $activity = GraphTraversalActivity::query()->create(['name' => 'Activity']);
    $activity->subject()->associate($child);
    $activity->save();

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->zeroOrMoreTimes();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($activity, ['subject'], 1, 10, 25, 'summary', request());

    expect($data->nodes)->toHaveCount(2);
    expect($data->edges)->toHaveCount(1);
    expect($data->edges[0]->target)->toBe('app:graph_traversal_children:' . $child->getKey());
});
