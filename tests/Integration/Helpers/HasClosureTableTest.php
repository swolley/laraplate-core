<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\TreeCollection;
use Modules\Core\Tests\Stubs\ClosureTreeStubModel;


beforeEach(function (): void {
    Schema::dropIfExists('closure_tree_nodes_closure');
    Schema::dropIfExists('closure_tree_nodes');

    Schema::create('closure_tree_nodes', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->timestamps();
    });

    Schema::create('closure_tree_nodes_closure', function (Blueprint $table): void {
        $table->unsignedBigInteger('ancestor_id');
        $table->unsignedBigInteger('descendant_id');
        $table->unsignedInteger('depth');
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

it('rebuilds closure table from root nodes', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $child = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    ClosureTreeStubModel::rebuildClosure();

    $rows = DB::table('closure_tree_nodes_closure')->orderBy('ancestor_id')->orderBy('descendant_id')->get();

    expect($rows)->not->toBeEmpty()
        ->and($child->fresh()->getDepth())->toBeInt();
});

it('computes depth with cache and memory cache', function (): void {
    Cache::flush();

    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    ClosureTreeStubModel::rebuildClosure();

    $depth = $root->fresh()->getDepth();
    expect($depth)->toBe(0);

    $depth_again = $root->fresh()->getDepth();
    expect($depth_again)->toBe(0);
});

it('resolves parent belongs-to relationship', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $child = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    $child = $child->fresh();

    expect($child->parent())->toBeInstanceOf(BelongsTo::class)
        ->and($child->parent)->not->toBeNull()
        ->and($child->parent->is($root))->toBeTrue();
});

it('detects root leaf and sibling relationships', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $a = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);
    $b = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    expect($root->isRoot())->toBeTrue()
        ->and($root->isLeaf())->toBeFalse()
        ->and($a->isSiblingOf($b))->toBeTrue()
        ->and($a->isSiblingOf($a))->toBeFalse();
});

it('detects ancestor and descendant via closure', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $child = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    ClosureTreeStubModel::rebuildClosure();

    $root = $root->fresh();
    $child = $child->fresh();

    expect($child->isDescendantOf($root))->toBeTrue()
        ->and($root->isAncestorOf($child))->toBeTrue()
        ->and($root->isDescendantOf($child))->toBeFalse();
});

it('moves node to new parent when allowed', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $a = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);
    $b = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    ClosureTreeStubModel::rebuildClosure();

    $a = $a->fresh();
    $b = $b->fresh();

    expect($a->moveTo($b))->toBeTrue()
        ->and($a->fresh()->parent_id)->toBe($b->id);
});

it('refuses move when target is descendant', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $child = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    ClosureTreeStubModel::rebuildClosure();

    $root = $root->fresh();
    $child = $child->fresh();

    expect($root->moveTo($child))->toBeFalse();
});

it('returns tree collection from newCollection', function (): void {
    $col = (new ClosureTreeStubModel)->newCollection([]);
    expect($col)->toBeInstanceOf(TreeCollection::class);
});

it('applies scopes withClosure tree and withBloodline', function (): void {
    expect(ClosureTreeStubModel::query()->withClosure()->toSql())->toBeString()
        ->and(ClosureTreeStubModel::query()->tree()->toSql())->toBeString()
        ->and(ClosureTreeStubModel::query()->withSiblings()->toSql())->toBeString()
        ->and(ClosureTreeStubModel::query()->withBloodline()->toSql())->toBeString();
});

it('exposes closure relation and hasMany sibling helpers on the tree', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);
    ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    $root = $root->fresh();
    expect($root)->not->toBeNull();

    expect($root->closure())->toBeInstanceOf(BelongsToMany::class)
        ->and($root->siblingsAndSelf()->count())->toBe(2)
        ->and($root->siblings()->count())->toBe(2);
});

it('deletes closure rows when a node is deleted', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $child = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    ClosureTreeStubModel::rebuildClosure();

    $before = DB::table('closure_tree_nodes_closure')->count();
    $child->delete();
    $after = DB::table('closure_tree_nodes_closure')->count();

    expect($before)->toBeGreaterThan(0)
        ->and($after)->toBeLessThan($before);
});

it('aggregates bloodline collections', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $child = ClosureTreeStubModel::query()->create(['parent_id' => $root->id]);

    ClosureTreeStubModel::rebuildClosure();

    $child = $child->fresh();

    $bloodline = $child->bloodline();
    $bloodline_self = $child->bloodlineAndSelf();

    expect($bloodline->count())->toBeGreaterThan(0)
        ->and($bloodline_self->count())->toBeGreaterThan(0);
});
