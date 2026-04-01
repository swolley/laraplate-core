<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder as BaseSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\HasSeedersUtils;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\SeedersBulkStubModel;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    Schema::create('seeders_bulk_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('seeders_relation_parents', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('seeders_relation_children', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('note')->nullable();
        $table->timestamps();
    });

    Schema::create('seeders_relation_tags', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('seeders_relation_child_tag', function (Blueprint $table): void {
        $table->unsignedBigInteger('seeders_relation_child_id');
        $table->unsignedBigInteger('seeders_relation_tag_id');
        $table->string('meta')->nullable();
    });
});

final class SeedersRelationParentStubModel extends Illuminate\Database\Eloquent\Model
{
    protected $table = 'seeders_relation_parents';

    protected $fillable = ['name'];
}

final class SeedersRelationTagStubModel extends Illuminate\Database\Eloquent\Model
{
    protected $table = 'seeders_relation_tags';

    protected $fillable = ['name'];
}

final class SeedersRelationChildStubModel extends Illuminate\Database\Eloquent\Model
{
    protected $table = 'seeders_relation_children';

    protected $fillable = ['name'];

    public function parent(): Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SeedersRelationParentStubModel::class, 'parent_id');
    }

    public function tags(): Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            SeedersRelationTagStubModel::class,
            'seeders_relation_child_tag',
            'seeders_relation_child_id',
            'seeders_relation_tag_id',
        );
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
        $this->save();
    }
}

final class SeedersUtilsTestSeeder extends BaseSeeder
{
    use HasSeedersUtils;

    public function runCreate(): User
    {
        return $this->create(User::class, [
            'name' => 'Seeded User',
            'username' => 'seeded_user_' . uniqid(),
            'email' => 'seeded_' . uniqid('', true) . '@example.com',
            'password' => 'secret',
            'lang' => 'en',
        ]);
    }

    /**
     * @return Collection<int, SeedersBulkStubModel>
     */
    public function runCreateMany(int $count = 2): Collection
    {
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = ['name' => 'bulk-' . $i];
        }

        return $this->createMany(SeedersBulkStubModel::class, $items);
    }

    /**
     * @return Collection<int, SeedersBulkStubModel>
     */
    public function runCreateManyEmpty(): Collection
    {
        return $this->createMany(SeedersBulkStubModel::class, []);
    }

    public function runCreateWithCallableMethod(string $note): SeedersRelationChildStubModel
    {
        /** @var SeedersRelationChildStubModel */
        return $this->create(SeedersRelationChildStubModel::class, [
            'name' => 'child-note',
            'setNote' => fn () => $note,
        ]);
    }

    public function runCreateWithBelongsTo(SeedersRelationParentStubModel $parent): SeedersRelationChildStubModel
    {
        /** @var SeedersRelationChildStubModel */
        return $this->create(SeedersRelationChildStubModel::class, [
            'name' => 'child-parent',
            'parent' => $parent,
        ]);
    }

    /**
     * @param  Collection<int, SeedersRelationTagStubModel>  $tags
     */
    public function runCreateWithBelongsToMany(Collection $tags): SeedersRelationChildStubModel
    {
        /** @var SeedersRelationChildStubModel */
        return $this->create(SeedersRelationChildStubModel::class, [
            'name' => 'child-tags',
            'tags' => fn () => $tags,
        ]);
    }

    /**
     * @param  Collection<int, SeedersRelationTagStubModel>  $tags
     */
    public function runCreateWithBelongsToManyPivot(Collection $tags): SeedersRelationChildStubModel
    {
        /** @var SeedersRelationChildStubModel */
        return $this->create(SeedersRelationChildStubModel::class, [
            'name' => 'child-tags-pivot',
            'tags' => fn () => $tags,
        ], [
            'tags' => ['meta' => 'seeded'],
        ]);
    }

    public function runLogOperation(string $model): void
    {
        $this->logOperation($model);
    }

    public function setCommandForTests(Illuminate\Console\Command $command): void
    {
        $this->command = $command;
    }
}

it('create persists a user from attributes', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    $user = $seeder->runCreate();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->exists)->toBeTrue()
        ->and(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
});

it('createMany bulk inserts and assigns incrementing ids', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    $models = $seeder->runCreateMany(3);

    expect($models)->toHaveCount(3)
        ->and(SeedersBulkStubModel::query()->count())->toBe(3);

    foreach ($models as $model) {
        expect($model->getKey())->not->toBeNull();
    }
});

it('createMany returns empty collection for empty items', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    expect($seeder->runCreateManyEmpty())->toBeEmpty();
});

it('create executes callable method relation fallback branch', function (): void {
    $seeder = new SeedersUtilsTestSeeder;

    $child = $seeder->runCreateWithCallableMethod('custom-note');

    expect($child->note)->toBe('custom-note');
});

it('create associates belongs-to relations', function (): void {
    $seeder = new SeedersUtilsTestSeeder;
    $parent = SeedersRelationParentStubModel::query()->create(['name' => 'parent']);

    $child = $seeder->runCreateWithBelongsTo($parent);

    expect($child->parent_id)->toBe($parent->id);
});

it('create syncs belongs-to-many relations with and without pivot values', function (): void {
    $seeder = new SeedersUtilsTestSeeder;
    $tags = collect([
        SeedersRelationTagStubModel::query()->create(['name' => 't1']),
        SeedersRelationTagStubModel::query()->create(['name' => 't2']),
    ]);

    $child_a = $seeder->runCreateWithBelongsToMany($tags);
    $child_b = $seeder->runCreateWithBelongsToManyPivot($tags);

    expect($child_a->tags()->count())->toBe(2)
        ->and($child_b->tags()->count())->toBe(2)
        ->and(
            Illuminate\Support\Facades\DB::table('seeders_relation_child_tag')
                ->where('seeders_relation_child_id', $child_b->id)
                ->where('meta', 'seeded')
                ->exists(),
        )->toBeTrue();
});

it('logOperation writes creating/updating output', function (): void {
    $seeder = new SeedersUtilsTestSeeder;
    $command = Mockery::mock(Illuminate\Console\Command::class);
    $command->shouldReceive('line')->twice();
    $seeder->setCommandForTests($command);

    $seeder->runLogOperation(SeedersRelationTagStubModel::class);
    SeedersRelationTagStubModel::query()->create(['name' => 'already']);
    $seeder->runLogOperation(SeedersRelationTagStubModel::class);
});
