<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

/**
 * @template TModel of Model
 */
trait HasClosureTable
{
    /**
     * In-memory cache for depth values during the request.
     */
    private static array $depth_cache = [];

    public static function rebuildClosure(): void
    {
        $table_name = new ReflectionClass(static::class)->newInstanceWithoutConstructor()->getTable() . '_closure';
        DB::table($table_name)->truncate();

        // Process only root nodes (nodes without parent) to avoid loading entire tree
        // Then recursively process children as needed
        $root_nodes = static::whereNull('parent_id')->with('children')->get();

        foreach ($root_nodes as $root) {
            self::insertClosures($root);
        }
    }

    /**
     * @return BelongsToMany<static>
     */
    public function ancestors(): BelongsToMany
    {
        $closureTable = $this->getClosureTable();
        $modelTable = $this->getModelTable();

        return $this->belongsToMany(
            self::class,
            $closureTable,
            'descendant_id',
            'ancestor_id',
        )
            ->withPivot('depth')
            ->orderBy($this->qualifyTreeColumn('depth', $closureTable), 'desc')
            ->select([
                $this->qualifyTreeColumn('*', $modelTable),
                $this->qualifyTreeColumn('depth', $closureTable),
            ]);
    }

    /**
     * @return BelongsToMany<static>
     */
    public function descendants(): BelongsToMany
    {
        $closureTable = $this->getClosureTable();
        $modelTable = $this->getModelTable();

        return $this->belongsToMany(
            self::class,
            $closureTable,
            'ancestor_id',
            'descendant_id',
        )
            ->withPivot('depth')
            ->orderBy($this->qualifyTreeColumn('depth', $closureTable), 'asc')
            ->select([
                $this->qualifyTreeColumn('*', $modelTable),
                $this->qualifyTreeColumn('depth', $closureTable),
            ]);
    }

    /**
     * @return BelongsToMany<static>
     */
    public function closure(): BelongsToMany
    {
        $closureTable = $this->getClosureTable();

        return $this->belongsToMany(
            static::class,
            $closureTable,
            'descendant_id',
            'ancestor_id',
        )
            ->withPivot('depth')
            ->from('categories as closure_categories')
            ->select('closure_categories.*');
    }

    /**
     * @return HasMany<static>
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * @return BelongsTo<static>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * @return HasMany<static>
     */
    public function siblings(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')
            ->where('id', '!=', $this->id);
    }

    /**
     * @return HasMany<static>
     */
    public function siblingsAndSelf(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * @return Collection<int, static>
     */
    public function bloodline(): Collection
    {
        return $this->ancestors->reverse()->push($this)->merge($this->descendants);
    }

    /**
     * @return Collection<int, static>
     */
    public function bloodlineAndSelf(): Collection
    {
        return $this->ancestors->reverse()->push($this);
    }

    public function getDepth(): int
    {
        $cache_key = sprintf('%s.%s.depth', $this->getTable(), $this->id);

        // Check in-memory cache first
        if (isset(self::$depth_cache[$cache_key])) {
            return self::$depth_cache[$cache_key];
        }

        $closureTable = $this->getClosureTable();

        $depth = Cache::remember(
            $cache_key,
            now()->addHours(24),
            fn () => DB::table($closureTable)
                ->where($this->qualifyTreeColumn('ancestor_id', $closureTable), $this->id)
                ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $this->id)
                ->value($this->qualifyTreeColumn('depth', $closureTable)) ?? 0,
        );

        // Store in memory
        self::$depth_cache[$cache_key] = $depth;

        return $depth;
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    public function isDescendantOf(self $ancestor): bool
    {
        $closureTable = $this->getClosureTable();

        return DB::table($closureTable)
            ->where($this->qualifyTreeColumn('ancestor_id', $closureTable), $ancestor->id)
            ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $this->id)
            ->where($this->qualifyTreeColumn('depth', $closureTable), '>', 0)
            ->exists();
    }

    public function isAncestorOf(self $descendant): bool
    {
        $closureTable = $this->getClosureTable();

        return DB::table($closureTable)
            ->where($this->qualifyTreeColumn('ancestor_id', $closureTable), $this->id)
            ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $descendant->id)
            ->where($this->qualifyTreeColumn('depth', $closureTable), '>', 0)
            ->exists();
    }

    public function isSiblingOf(self $sibling): bool
    {
        return $this->parent_id === $sibling->parent_id && $this->id !== $sibling->id;
    }

    public function moveTo(self $newParent): bool
    {
        if ($this->isDescendantOf($newParent)) {
            return false;
        }

        DB::transaction(function () use ($newParent): void {
            $this->parent_id = $newParent->id;
            $this->save();
            $this->updateClosureTable();
        });

        return true;
    }

    public function newCollection(array $models = []): TreeCollection
    {
        return new TreeCollection($models);
    }

    protected static function insertClosures($model, $ancestors = []): void
    {
        $selfRow = [
            'ancestor_id' => $model->id,
            'descendant_id' => $model->id,
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $rows = [$selfRow];

        foreach ($ancestors as $i => $ancestor) {
            $rows[] = [
                'ancestor_id' => $ancestor->id,
                'descendant_id' => $model->id,
                'depth' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $table_name = new ReflectionClass(static::class)->newInstanceWithoutConstructor()->getTable() . '_closure';

        // Batch insert for better performance
        if (! empty($rows)) {
            DB::table($table_name)->insert($rows);
        }

        // Load children only when needed (lazy loading)
        // This prevents loading entire tree in memory at once
        $children = $model->children()->with('children')->get();

        foreach ($children as $child) {
            self::insertClosures($child, array_merge([$model], $ancestors));
        }
    }

    protected static function bootHasClosureTable(): void
    {
        static::created(function ($model): void {
            $model->updateClosureTable();
        });

        static::updated(function ($model): void {
            if ($model->isDirty('parent_id')) {
                $model->updateClosureTable();
            }
        });

        static::deleted(function ($model): void {
            $table_name = new ReflectionClass(static::class)->newInstanceWithoutConstructor()->getTable() . '_closure';
            DB::table($table_name)
                ->where('descendant_id', $model->id)
                ->orWhere('ancestor_id', $model->id)
                ->delete();
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withClosure(Builder $query): Builder
    {
        return $query->with(['closure', 'ancestors']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function tree(Builder $query): Builder
    {
        $closureTable = $this->getClosureTable();
        // $modelTable = $this->getModelTable();

        return $query->withClosure()
            ->whereDoesntHave('ancestors')
            ->with([
                'closure' => fn ($query) => $query->orderBy($this->qualifyTreeColumn('depth', $closureTable)),
            ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withSiblings(Builder $query): Builder
    {
        return $query->with(['siblings', 'siblingsAndSelf']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withBloodline(Builder $query): Builder
    {
        return $query->with(['ancestors', 'descendants']);
    }

    protected function getClosureTable(): string
    {
        return $this->getTable() . '_closure';
    }

    protected function getModelTable(): string
    {
        return $this->getTable();
    }

    protected function qualifyTreeColumn(string $column, ?string $table = null): string
    {
        $table ??= $this->getModelTable();

        return sprintf('%s.%s', $table, $column);
    }

    protected function updateClosureTable(): void
    {
        $closureTable = $this->getClosureTable();

        // Delete old closure entries for this node
        DB::table($closureTable)
            ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $this->id)
            ->delete();

        // Prepare rows for batch insert
        $rows = [
            [
                'ancestor_id' => $this->id,
                'descendant_id' => $this->id,
                'depth' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert records for all ancestors (batch insert for better performance)
        if ($this->parent_id) {
            $ancestors = DB::table($closureTable)
                ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $this->parent_id)
                ->get();

            foreach ($ancestors as $ancestor) {
                $rows[] = [
                    'ancestor_id' => $ancestor->ancestor_id,
                    'descendant_id' => $this->id,
                    'depth' => $ancestor->depth + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Batch insert all rows at once (more efficient than multiple inserts)
        if (! empty($rows)) {
            DB::table($closureTable)->insert($rows);
        }

        // Note: When a node moves, its descendants' closure entries also need to be updated
        // This is currently handled by calling rebuildClosure() when needed, or can be
        // optimized in the future with a more efficient SQL-based approach

        // Clear in-memory cache for this model
        $cache_key = sprintf('%s.%s.depth', $this->getTable(), $this->id);
        unset(self::$depth_cache[$cache_key]);

        // Clear external cache
        Cache::forget($cache_key);
    }
}
