<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Core\Casts\FieldType;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\TreeCollection;
use Modules\Core\Models\Field;
use Modules\Core\Observers\FieldObserver;
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

    config()->set('database.connections.affinity', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('affinity');

    Schema::connection('affinity')->create('closure_tree_nodes', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->timestamps();
    });

    Schema::connection('affinity')->create('closure_tree_nodes_closure', function (Blueprint $table): void {
        $table->unsignedBigInteger('ancestor_id');
        $table->unsignedBigInteger('descendant_id');
        $table->unsignedInteger('depth');
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

afterEach(function (): void {
    DB::disconnect('affinity');
    DB::purge('affinity');
});

it('writes closure rows on the model connection', function (): void {
    $model = (new ClosureTreeStubModel)->setConnection('affinity');
    $root = $model->newQuery()->create(['parent_id' => null]);
    $model->newQuery()->create(['parent_id' => $root->id]);

    expect(DB::connection('affinity')->table('closure_tree_nodes_closure')->count())->toBe(3)
        ->and(DB::table('closure_tree_nodes_closure')->count())->toBe(0);
});

it('moves affinity tree nodes inside the affinity transaction', function (): void {
    $model = (new ClosureTreeStubModel)->setConnection('affinity');
    $root = $model->newQuery()->create(['parent_id' => null]);
    $first_child = $model->newQuery()->create(['parent_id' => $root->id]);
    $second_child = $model->newQuery()->create(['parent_id' => $root->id]);

    $affinity_transaction_started = false;
    $default_transaction_started = false;
    DB::connection('affinity')->beforeStartingTransaction(static function () use (&$affinity_transaction_started): void {
        $affinity_transaction_started = true;
    });
    DB::connection()->beforeStartingTransaction(static function () use (&$default_transaction_started): void {
        $default_transaction_started = true;
    });

    expect($first_child->moveTo($second_child))->toBeTrue();

    expect($affinity_transaction_started)->toBeTrue()
        ->and($default_transaction_started)->toBeFalse();
});

it('rejects moving tree nodes across database connections before mutation', function (): void {
    $affinity_model = (new ClosureTreeStubModel)->setConnection('affinity');
    $node = $affinity_model->newQuery()->create(['parent_id' => null]);
    $default_parent = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $affinity_closure_count = DB::connection('affinity')->table('closure_tree_nodes_closure')->count();
    $default_closure_count = DB::table('closure_tree_nodes_closure')->count();

    expect(fn (): bool => $node->moveTo($default_parent))
        ->toThrow(LogicException::class, 'Cannot move tree nodes across multiple database connections.');

    expect($node->fresh()->parent_id)->toBeNull()
        ->and(DB::connection('affinity')->table('closure_tree_nodes_closure')->count())->toBe($affinity_closure_count)
        ->and(DB::table('closure_tree_nodes_closure')->count())->toBe($default_closure_count);
});

it('checks linked fields on the field model connection', function (): void {
    Schema::connection('affinity')->create(CoreTables::Fieldables->value, function (Blueprint $table): void {
        $table->unsignedBigInteger('field_id');
    });
    DB::connection('affinity')->table(CoreTables::Fieldables->value)->insert(['field_id' => 999_999]);

    $field = (new Field)->setConnection('affinity');
    $field->forceFill([
        'id' => 999_999,
        'name' => 'affinity_field',
        'type' => FieldType::Text,
    ]);
    $field->exists = true;
    $field->syncOriginal();
    $field->type = FieldType::Number;

    expect(fn (): mixed => (new FieldObserver)->updating($field))
        ->toThrow(ValidationException::class);
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

it('isolates depth caches for identical table and ids on different connections', function (): void {
    Cache::flush();

    $node_id = 999_999;
    DB::table('closure_tree_nodes')->insert(['id' => $node_id, 'parent_id' => null]);
    DB::connection('affinity')->table('closure_tree_nodes')->insert(['id' => $node_id, 'parent_id' => null]);
    DB::table('closure_tree_nodes_closure')->insert([
        'ancestor_id' => $node_id,
        'descendant_id' => $node_id,
        'depth' => 2,
    ]);
    DB::connection('affinity')->table('closure_tree_nodes_closure')->insert([
        'ancestor_id' => $node_id,
        'descendant_id' => $node_id,
        'depth' => 7,
    ]);

    $default_node = (new ClosureTreeStubModel)->setRawAttributes(['id' => $node_id, 'parent_id' => null], true);
    $default_node->exists = true;
    $affinity_node = (new ClosureTreeStubModel)->setConnection('affinity');
    $affinity_node->setRawAttributes(['id' => $node_id, 'parent_id' => null], true);
    $affinity_node->exists = true;

    expect($default_node->getDepth())->toBe(2)
        ->and($affinity_node->getDepth())->toBe(7);

    DB::connection('affinity')->table('closure_tree_nodes_closure')
        ->where('ancestor_id', $node_id)
        ->where('descendant_id', $node_id)
        ->update(['depth' => 9]);

    $default_parent = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    expect($default_node->moveTo($default_parent))->toBeTrue()
        ->and($default_node->fresh()->getDepth())->toBe(0)
        ->and($affinity_node->getDepth())->toBe(7);
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

it('rebuilds a larger closure tree without eager loading recursive children', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $parent_id = $root->id;

    for ($i = 0; $i < 120; $i++) {
        $node = ClosureTreeStubModel::query()->create(['parent_id' => $parent_id]);
        $parent_id = $node->id;
    }

    DB::enableQueryLog();

    ClosureTreeStubModel::rebuildClosure();

    $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect(DB::table('closure_tree_nodes_closure')->count())->toBeGreaterThan(120)
        ->and($queries)->not->toContain('select * from "closure_tree_nodes" where "closure_tree_nodes"."parent_id" in');
});
