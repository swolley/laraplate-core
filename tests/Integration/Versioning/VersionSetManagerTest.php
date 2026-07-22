<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Models\Version;
use Modules\Core\Models\VersionSet;
use Modules\Core\Versioning\ActiveVersionSet;
use Modules\Core\Versioning\Contracts\VersionSetManagerInterface;
use Modules\Core\Versioning\Data\VersionSetOptions;
use Modules\Core\Versioning\Data\VersionSetRoot;
use Modules\Core\Versioning\Exceptions\DirtyActiveVersionSetRootException;
use Modules\Core\Versioning\Exceptions\InvalidRevertedVersionSetException;
use Modules\Core\Versioning\Exceptions\MultipleVersionConnectionsNotSupportedException;
use Modules\Core\Versioning\Exceptions\PendingVersionSequenceException;
use Modules\Core\Versioning\Exceptions\VersionSequenceMismatchException;
use Modules\Core\Versioning\Exceptions\VersionSetOptionsMismatchException;
use Modules\Core\Versioning\Exceptions\VersionSetRootMismatchException;
use Overtrue\LaravelVersionable\VersionStrategy;

final class VersionSetManagerArticle extends Model
{
    public const string TABLE = 'core_test_version_set_articles';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return HasMany<VersionSetManagerArticle>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}

final class VersionSetManagerUuidArticle extends Model
{
    public const string TABLE = 'core_test_version_set_uuid_articles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = self::TABLE;

    protected $guarded = [];
}

final class VersionSetManagerAffinityArticle extends Model
{
    public const string TABLE = 'core_test_affinity_version_set_articles';

    protected $connection = 'version_set_affinity';

    protected $table = self::TABLE;

    protected $guarded = [];
}

final class ObserveVersionSetStateJob implements ShouldQueue
{
    public static ?bool $sawActiveSet = null;

    public function handle(VersionSetManagerInterface $manager): void
    {
        self::$sawActiveSet = $manager->current() !== null;
    }
}

beforeEach(function (): void {
    Schema::create(VersionSetManagerArticle::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->timestamps();
    });

    Schema::create(VersionSetManagerUuidArticle::TABLE, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('title');
        $table->timestamps();
    });

    config()->set('database.connections.version_set_affinity', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('version_set_affinity');
    createManagerTestAffinitySchema();
    ObserveVersionSetStateJob::$sawActiveSet = null;
});

afterEach(function (): void {
    Schema::dropIfExists(VersionSetManagerUuidArticle::TABLE);
    Schema::dropIfExists(VersionSetManagerArticle::TABLE);
    Schema::connection('version_set_affinity')->dropIfExists(CoreTables::Versions->value);
    Schema::connection('version_set_affinity')->dropIfExists(CoreTables::VersionSets->value);
    Schema::connection('version_set_affinity')->dropIfExists(VersionSetManagerAffinityArticle::TABLE);
    DB::disconnect('version_set_affinity');
    DB::purge('version_set_affinity');
});

function createManagerTestAffinitySchema(): void
{
    $schema = Schema::connection('version_set_affinity');

    $schema->create(VersionSetManagerAffinityArticle::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    $schema->create(CoreTables::VersionSets->value, function (Blueprint $table): void {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('root_type')->nullable();
        $table->string('root_id')->nullable();
        $table->string('root_connection_ref')->nullable();
        $table->string('root_table_ref')->nullable();
        $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();
        $table->string('kind');
        $table->string('reason')->nullable();
        $table->unsignedBigInteger('reverted_from_set_id')->nullable();
        $table->timestamps();
    });

    $schema->create(CoreTables::Versions->value, function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('version_set_id')->nullable();
        $table->unsignedInteger('sequence')->nullable();
        $table->string('change_type')->nullable();
        $table->string('relation_path')->nullable();
        $table->json('subject_key')->nullable();
        $table->string('connection_ref')->nullable();
        $table->string('table_ref')->nullable();
        $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();
        $table->unsignedBigInteger('versionable_id');
        $table->string('versionable_type');
        $table->json('original_contents')->nullable();
        $table->json('contents')->nullable();
        $table->string('version_strategy');
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['version_set_id', 'sequence']);
        $table->foreign('version_set_id')
            ->references('id')
            ->on(CoreTables::VersionSets->value)
            ->cascadeOnDelete();
    });
}

function recordManagerTestVersion(ActiveVersionSet $active, Model $root): Version
{
    $version_set = $active->versionSet();
    $version = new Version;
    $version->setConnection($active->connectionName());
    $version->forceFill([
        'version_set_id' => $version_set->getKey(),
        'sequence' => $active->nextSequence(),
        'change_type' => VersionChangeType::Updated,
        'versionable_type' => $root->getMorphClass(),
        'versionable_id' => $root->getKey(),
        'original_contents' => ['title' => $root->getRawOriginal('title')],
        'contents' => ['title' => $root->getAttribute('title')],
        'version_strategy' => VersionStrategy::DIFF,
    ])->saveOrFail();
    $active->markVersionWritten();

    return $version;
}

it('synchronizes the root from its locked snapshot while preserving intentional dirty values', function (): void {
    $article = VersionSetManagerArticle::query()->create([
        'title' => 'Before',
        'status' => 'draft',
    ]);
    $stale = $article->fresh();
    VersionSetManagerArticle::query()->whereKey($article)->update([
        'title' => 'Concurrent',
        'status' => 'published',
    ]);
    $stale->setAttribute('status', 'review');

    app(VersionSetManagerInterface::class)->run(
        VersionSetRoot::forModel($stale),
        function () use ($stale): void {
            expect($stale->title)->toBe('Concurrent')
                ->and($stale->getRawOriginal('title'))->toBe('Concurrent')
                ->and($stale->status)->toBe('review')
                ->and($stale->getRawOriginal('status'))->toBe('published')
                ->and($stale->isDirty('status'))->toBeTrue()
                ->and($stale->isDirty('title'))->toBeFalse();
        },
    );
});

it('invalidates relations loaded before locking and reloads their current state', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Root']);
    VersionSetManagerArticle::query()->create([
        'parent_id' => $article->getKey(),
        'title' => 'First child',
    ]);
    $stale = $article->fresh()->load('children');
    VersionSetManagerArticle::query()->create([
        'parent_id' => $article->getKey(),
        'title' => 'Second child',
    ]);

    expect($stale->relationLoaded('children'))->toBeTrue()
        ->and($stale->children)->toHaveCount(1);

    app(VersionSetManagerInterface::class)->run(
        VersionSetRoot::forModel($stale),
        function () use ($stale): void {
            expect($stale->relationLoaded('children'))->toBeFalse()
                ->and($stale->children)->toHaveCount(2);
        },
    );
});

it('commits business data and one version set on the root connection', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);
    $transaction_level = null;

    $result = $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article, &$transaction_level): string {
            $transaction_level = $article->getConnection()->transactionLevel();
            $article->updateOrFail(['title' => 'After']);
            recordManagerTestVersion($active, $article);

            return 'done';
        },
        new VersionSetOptions(kind: VersionSetKind::Change, reason: 'manager-test', actor: 42),
    );

    $set = VersionSet::query()->sole();

    expect($result)->toBe('done')
        ->and($transaction_level)->toBeGreaterThan(0)
        ->and($article->fresh()->title)->toBe('After')
        ->and($set->root_type)->toBe($article->getMorphClass())
        ->and($set->root_id)->toBe((string) $article->getKey())
        ->and($set->root_connection_ref)->toBe($article->getConnection()->getName())
        ->and($set->root_table_ref)->toBe($article->getTable())
        ->and($set->kind)->toBe(VersionSetKind::Change)
        ->and($set->reason)->toBe('manager-test')
        ->and($set->getAttribute(config('versionable.user_foreign_key', 'user_id')))->toBe(42)
        ->and($set->versions()->sole()->sequence)->toBe(1)
        ->and($manager->current())->toBeNull();
});

it('rolls back business data and its version set together', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => 'Changed']);
            recordManagerTestVersion($active, $article);

            throw new RuntimeException('abort');
        },
    ))->toThrow(RuntimeException::class, 'abort');

    expect($article->fresh()->title)->toBe('Before')
        ->and(VersionSet::query()->count())->toBe(0)
        ->and(Version::query()->whereNotNull('version_set_id')->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('joins nested calls for the same root and allocates one ordered set', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);
    $outer_state_id = null;
    $nested_state_id = null;

    $manager->run(VersionSetRoot::forModel($article), function (ActiveVersionSet $active) use (
        $article,
        $manager,
        &$outer_state_id,
        &$nested_state_id,
    ): void {
        $outer_state_id = spl_object_id($active);
        $article->updateOrFail(['title' => 'First']);
        recordManagerTestVersion($active, $article);

        $manager->run(VersionSetRoot::forModel($article), function (ActiveVersionSet $nested) use (
            $article,
            &$nested_state_id,
        ): void {
            $nested_state_id = spl_object_id($nested);
            $article->updateOrFail(['title' => 'Second']);
            recordManagerTestVersion($nested, $article);
        });
    });

    expect(VersionSet::query()->count())->toBe(1)
        ->and($outer_state_id)->toBe($nested_state_id)
        ->and(VersionSet::query()->sole()->versions()->pluck('sequence')->all())->toBe([1, 2])
        ->and($manager->current())->toBeNull();
});

it('accepts semantically equivalent explicit options in a nested scope', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);
    $outer_options = new VersionSetOptions(reason: 'nested', actor: 42);
    $nested_ran = false;

    $manager->run(
        VersionSetRoot::forModel($article),
        function () use ($article, $manager, &$nested_ran): void {
            $manager->run(
                VersionSetRoot::forModel($article),
                function () use (&$nested_ran): void {
                    $nested_ran = true;
                },
                new VersionSetOptions(reason: 'nested', actor: 42),
            );
        },
        $outer_options,
    );

    expect($nested_ran)->toBeTrue();
});

it('synchronizes a distinct nested root instance while preserving only its intentional dirty values', function (): void {
    $article = VersionSetManagerArticle::query()->create([
        'title' => 'Before',
        'status' => 'draft',
    ]);
    VersionSetManagerArticle::query()->create([
        'parent_id' => $article->getKey(),
        'title' => 'First child',
    ]);
    $nested_root = $article->fresh()->load('children');
    $nested_root->setAttribute('status', 'review');
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($article), function () use (
        $article,
        $manager,
        $nested_root,
    ): void {
        $article->updateOrFail([
            'title' => 'Outer current',
            'status' => 'published',
        ]);
        VersionSetManagerArticle::query()->create([
            'parent_id' => $article->getKey(),
            'title' => 'Second child',
        ]);

        $manager->run(VersionSetRoot::forModel($nested_root), function () use ($nested_root): void {
            expect($nested_root->title)->toBe('Outer current')
                ->and($nested_root->getRawOriginal('title'))->toBe('Outer current')
                ->and($nested_root->isDirty('title'))->toBeFalse()
                ->and($nested_root->status)->toBe('review')
                ->and($nested_root->getRawOriginal('status'))->toBe('published')
                ->and($nested_root->isDirty('status'))->toBeTrue()
                ->and($nested_root->relationLoaded('children'))->toBeFalse()
                ->and($nested_root->children)->toHaveCount(2);
        });
    });
});

it('rejects a distinct nested instance while the active root has unsaved changes', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $nested_root = $article->fresh();
    $manager = app(VersionSetManagerInterface::class);
    $nested_ran = false;

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function () use ($article, $manager, $nested_root, &$nested_ran): void {
            $article->setAttribute('title', 'Unsaved outer change');

            $manager->run(
                VersionSetRoot::forModel($nested_root),
                function () use (&$nested_ran): void {
                    $nested_ran = true;
                },
            );
        },
    ))->toThrow(DirtyActiveVersionSetRootException::class);

    expect($nested_ran)->toBeFalse()
        ->and($article->fresh()->title)->toBe('Before')
        ->and($manager->current())->toBeNull();
});

it('rejects differing explicit options in a nested scope before its operation runs', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);
    $nested_ran = false;

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        fn () => $manager->run(
            VersionSetRoot::forModel($article),
            function () use (&$nested_ran): void {
                $nested_ran = true;
            },
            new VersionSetOptions(reason: 'different'),
        ),
        new VersionSetOptions(reason: 'outer'),
    ))->toThrow(VersionSetOptionsMismatchException::class);

    expect($nested_ran)->toBeFalse()
        ->and($manager->current())->toBeNull();
});

it('rejects a nested different root before its operation runs', function (): void {
    $first = VersionSetManagerArticle::query()->create(['title' => 'First']);
    $second = VersionSetManagerArticle::query()->create(['title' => 'Second']);
    $manager = app(VersionSetManagerInterface::class);
    $nested_ran = false;

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($first),
        fn () => $manager->run(
            VersionSetRoot::forModel($second),
            function () use (&$nested_ran): void {
                $nested_ran = true;
            },
        ),
    ))->toThrow(VersionSetRootMismatchException::class);

    expect($nested_ran)->toBeFalse()
        ->and(VersionSet::query()->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('creates one set per root for sequential batch operations', function (): void {
    $articles = collect([
        VersionSetManagerArticle::query()->create(['title' => 'First']),
        VersionSetManagerArticle::query()->create(['title' => 'Second']),
    ]);
    $manager = app(VersionSetManagerInterface::class);

    $articles->each(function (VersionSetManagerArticle $article) use ($manager): void {
        $manager->run(VersionSetRoot::forModel($article), function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => $article->title . ' changed']);
            recordManagerTestVersion($active, $article);
        });
    });

    expect(VersionSet::query()->count())->toBe(2)
        ->and(VersionSet::query()->pluck('root_id')->all())->toBe($articles->map(
            static fn (VersionSetManagerArticle $article): string => (string) $article->getKey(),
        )->all());
});

it('persists revert metadata on the new forward version set', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($article), function (ActiveVersionSet $active) use ($article): void {
        $article->updateOrFail(['title' => 'After']);
        recordManagerTestVersion($active, $article);
    });
    $target = VersionSet::query()->sole();

    $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => 'Before']);
            recordManagerTestVersion($active, $article);
        },
        new VersionSetOptions(
            kind: VersionSetKind::Revert,
            reason: 'restore-test',
            actor: 42,
            revertedFrom: $target,
        ),
    );

    $revert = VersionSet::query()->whereKeyNot($target->getKey())->sole();

    expect($revert->kind)->toBe(VersionSetKind::Revert)
        ->and($revert->reverted_from_set_id)->toBe($target->getKey())
        ->and($revert->revertedFrom->is($target))->toBeTrue();
});

it('rejects an integer revert target owned by another root', function (): void {
    $source = VersionSetManagerArticle::query()->create(['title' => 'Source']);
    $other = VersionSetManagerArticle::query()->create(['title' => 'Other']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($source), function (ActiveVersionSet $active) use ($source): void {
        $source->updateOrFail(['title' => 'Source changed']);
        recordManagerTestVersion($active, $source);
    });
    $target_id = VersionSet::query()->sole()->getKey();
    $operation_ran = false;

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($other),
        function () use (&$operation_ran): void {
            $operation_ran = true;
        },
        new VersionSetOptions(kind: VersionSetKind::Revert, revertedFrom: $target_id),
    ))->toThrow(InvalidRevertedVersionSetException::class);

    expect($operation_ran)->toBeFalse()
        ->and(VersionSet::query()->count())->toBe(1);
});

it('rejects a revert model loaded from another connection despite a matching local id', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($article), function (ActiveVersionSet $active) use ($article): void {
        $article->updateOrFail(['title' => 'After']);
        recordManagerTestVersion($active, $article);
    });
    $local_target = VersionSet::query()->sole();

    $foreign_target = new VersionSet;
    $foreign_target->setConnection('version_set_affinity');
    $foreign_target->forceFill([
        'id' => $local_target->getKey(),
        'uuid' => (string) Str::uuid(),
        'root_type' => $local_target->root_type,
        'root_id' => $local_target->root_id,
        'root_connection_ref' => $local_target->root_connection_ref,
        'root_table_ref' => $local_target->root_table_ref,
        'kind' => VersionSetKind::Change,
    ])->saveOrFail();
    $operation_ran = false;

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function () use (&$operation_ran): void {
            $operation_ran = true;
        },
        new VersionSetOptions(kind: VersionSetKind::Revert, revertedFrom: $foreign_target),
    ))->toThrow(InvalidRevertedVersionSetException::class);

    expect($operation_ran)->toBeFalse()
        ->and(VersionSet::query()->count())->toBe(1);
});

it('does not persist a set for an empty scope', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($article), static fn (): null => null);

    expect(VersionSet::query()->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('removes a lazily materialized set when no version row was written', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(
        VersionSetRoot::forModel($article),
        static fn (ActiveVersionSet $active): VersionSet => $active->versionSet(),
    );

    expect(VersionSet::query()->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('rolls back when a saved version sequence is left unconfirmed', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => 'After']);
            $version_set = $active->versionSet();
            $version = new Version;
            $version->setConnection($active->connectionName());
            $version->forceFill([
                'version_set_id' => $version_set->getKey(),
                'sequence' => $active->nextSequence(),
                'change_type' => VersionChangeType::Updated,
                'versionable_type' => $article->getMorphClass(),
                'versionable_id' => $article->getKey(),
                'original_contents' => ['title' => 'Before'],
                'contents' => ['title' => 'After'],
                'version_strategy' => VersionStrategy::DIFF,
            ])->saveOrFail();

            try {
                throw new RuntimeException('intercepted after save');
            } catch (RuntimeException) {
                // The missing mark must still invalidate the outer scope.
            }
        },
    ))->toThrow(PendingVersionSequenceException::class);

    expect($article->fresh()->title)->toBe('Before')
        ->and(VersionSet::query()->count())->toBe(0)
        ->and(Version::query()->whereNotNull('version_set_id')->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('rolls back when a confirmed sequence has no corresponding version row', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => 'After']);
            recordManagerTestVersion($active, $article);
            $active->nextSequence();
            $active->markVersionWritten();
        },
    ))->toThrow(VersionSequenceMismatchException::class);

    expect($article->fresh()->title)->toBe('Before')
        ->and(VersionSet::query()->count())->toBe(0)
        ->and(Version::query()->whereNotNull('version_set_id')->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('rolls back when an extra version row has no confirmed sequence', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => 'After']);
            recordManagerTestVersion($active, $article);

            $version = new Version;
            $version->setConnection($active->connectionName());
            $version->forceFill([
                'version_set_id' => $active->versionSet()->getKey(),
                'sequence' => 2,
                'change_type' => VersionChangeType::Updated,
                'versionable_type' => $article->getMorphClass(),
                'versionable_id' => $article->getKey(),
                'original_contents' => ['title' => 'After'],
                'contents' => ['title' => 'Unexpected'],
                'version_strategy' => VersionStrategy::DIFF,
            ])->saveOrFail();
        },
    ))->toThrow(VersionSequenceMismatchException::class);

    expect($article->fresh()->title)->toBe('Before')
        ->and(VersionSet::query()->count())->toBe(0)
        ->and(Version::query()->whereNotNull('version_set_id')->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('counts a soft deleted extra row as an unconfirmed persisted sequence', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => 'After']);
            recordManagerTestVersion($active, $article);

            $version = new Version;
            $version->setConnection($active->connectionName());
            $version->forceFill([
                'version_set_id' => $active->versionSet()->getKey(),
                'sequence' => 2,
                'change_type' => VersionChangeType::Updated,
                'versionable_type' => $article->getMorphClass(),
                'versionable_id' => $article->getKey(),
                'original_contents' => ['title' => 'After'],
                'contents' => ['title' => 'Unexpected'],
                'version_strategy' => VersionStrategy::DIFF,
            ])->saveOrFail();
            $version->deleteOrFail();
        },
    ))->toThrow(VersionSequenceMismatchException::class);

    expect($article->fresh()->title)->toBe('Before')
        ->and(VersionSet::query()->count())->toBe(0)
        ->and(Version::withTrashed()->whereNotNull('version_set_id')->count())->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('commits root set and version rows on a real secondary connection', function (): void {
    $article = VersionSetManagerAffinityArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($article), function (ActiveVersionSet $active) use ($article): void {
        $article->updateOrFail(['title' => 'After']);
        recordManagerTestVersion($active, $article);
    });

    expect(VersionSetManagerAffinityArticle::query()->sole()->title)->toBe('After')
        ->and(VersionSet::on('version_set_affinity')->count())->toBe(1)
        ->and(Version::on('version_set_affinity')->count())->toBe(1)
        ->and(VersionSet::query()->count())->toBe(0)
        ->and(Version::query()->whereNotNull('version_set_id')->count())->toBe(0);
});

it('rolls back root set and version rows on a real secondary connection', function (): void {
    $article = VersionSetManagerAffinityArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function (ActiveVersionSet $active) use ($article): void {
            $article->updateOrFail(['title' => 'After']);
            recordManagerTestVersion($active, $article);

            throw new RuntimeException('abort affinity');
        },
    ))->toThrow(RuntimeException::class, 'abort affinity');

    expect(VersionSetManagerAffinityArticle::query()->sole()->title)->toBe('Before')
        ->and(VersionSet::on('version_set_affinity')->count())->toBe(0)
        ->and(Version::on('version_set_affinity')->count())->toBe(0);
});

it('rejects a second pre-enlisted connection before its callback first query', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);
    $second_callback_ran = false;
    $second_query_count = 0;
    DB::connection('version_set_affinity')->beforeExecuting(
        static function () use (&$second_query_count): void {
            $second_query_count++;
        },
    );

    expect(fn () => $manager->run(
        VersionSetRoot::forModel($article),
        function () use ($manager, &$second_callback_ran): void {
            $manager->enlist('version_set_affinity');
            $second_callback_ran = true;
            VersionSetManagerAffinityArticle::query()->count();
        },
    ))->toThrow(MultipleVersionConnectionsNotSupportedException::class);

    expect($second_callback_ran)->toBeFalse()
        ->and($second_query_count)->toBe(0)
        ->and($manager->current())->toBeNull();
});

it('resets state after both successful and failing scopes', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);

    $manager->run(VersionSetRoot::forModel($article), static fn (): null => null);
    expect($manager->current())->toBeNull();

    try {
        $manager->run(
            VersionSetRoot::forModel($article),
            static fn () => throw new RuntimeException('failure'),
        );
    } catch (RuntimeException) {
        // The state assertion below is the behavior under test.
    }

    expect($manager->current())->toBeNull();
});

it('does not propagate active writable state into a separately executed job', function (): void {
    $article = VersionSetManagerArticle::query()->create(['title' => 'Before']);
    $manager = app(VersionSetManagerInterface::class);
    $serialized_job = null;

    $manager->run(VersionSetRoot::forModel($article), function () use (&$serialized_job): void {
        $serialized_job = serialize(new ObserveVersionSetStateJob);
    });

    app()->forgetScopedInstances();
    $job = unserialize($serialized_job, ['allowed_classes' => [ObserveVersionSetStateJob::class]]);
    expect($job)->toBeInstanceOf(ObserveVersionSetStateJob::class);
    $job->handle(app(VersionSetManagerInterface::class));

    expect(ObserveVersionSetStateJob::$sawActiveSet)->toBeFalse();
});

it('normalizes integer UUID and dynamic root identity metadata', function (): void {
    $integer = VersionSetManagerArticle::query()->create(['title' => 'Integer']);
    $uuid = (string) Str::uuid();
    $uuid_article = VersionSetManagerUuidArticle::query()->create(['id' => $uuid, 'title' => 'UUID']);
    $uuid_article->setTable('runtime_version_set_articles');

    $integer_root = VersionSetRoot::forModel($integer);
    $dynamic_root = VersionSetRoot::forModel($uuid_article);

    expect($integer_root->id())->toBe((string) $integer->getKey())
        ->and($dynamic_root->id())->toBe($uuid)
        ->and($dynamic_root->connectionName())->toBe($uuid_article->getConnection()->getName())
        ->and($dynamic_root->tableName())->toBe('runtime_version_set_articles')
        ->and($dynamic_root->type())->toBe($uuid_article->getMorphClass());
});
