<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeders;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder as BaseSeeder;
use Illuminate\Support\Collection;
use Modules\Core\Helpers\HasSeedersUtils;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\SeedersBulkStubModel;

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

    public function setCommandForTests(Command $command): void
    {
        $this->command = $command;
    }
}
