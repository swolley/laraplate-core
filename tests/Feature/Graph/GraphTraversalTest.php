<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Graph\GraphEntityResolver;
use Modules\Core\Graph\GraphNodeSerializer;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Graph\GraphRelationInspector;
use Modules\Core\Graph\GraphTraversal;
use Modules\Core\Services\Authorization\AuthorizationService;

uses(RefreshDatabase::class);

final class GraphTraversalParent extends Model
{
    protected $table = 'graph_traversal_parents';

    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(GraphTraversalChild::class, 'parent_id');
    }
}

final class GraphTraversalChild extends Model
{
    protected $table = 'graph_traversal_children';

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(GraphTraversalParent::class, 'parent_id');
    }
}

final class GraphTraversalActivity extends Model
{
    protected $table = 'graph_traversal_activities';

    protected $guarded = [];

    public function subject()
    {
        return $this->morphTo();
    }
}

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
