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

trait HasClosureTable
{
    public static function rebuildClosure(): void
    {
        $table_name = new static()->getTable() . '_closure';
        DB::table($table_name)->truncate();

        $models = static::with('children')->get()->keyBy('id');

        foreach ($models as $model) {
            self::insertClosures($model);
        }
    }

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

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    #[Scope]
    public function withClosure(Builder $query): Builder
    {
        return $query->with(['closure', 'ancestors']);
    }

    public function scopeTree(Builder $query): Builder
    {
        $closureTable = $this->getClosureTable();
        // $modelTable = $this->getModelTable();

        return $query->withClosure()
            ->whereDoesntHave('ancestors')
            ->with([
                'closure' => fn ($query) => $query->orderBy($this->qualifyTreeColumn('depth', $closureTable)),
            ]);
    }

    #[Scope]
    public function withSiblings(Builder $query): Builder
    {
        return $query->with(['siblings', 'siblingsAndSelf']);
    }

    #[Scope]
    public function withBloodline(Builder $query): Builder
    {
        return $query->with(['ancestors', 'descendants']);
    }

    public function siblings(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')
            ->where('id', '!=', $this->id);
    }

    public function siblingsAndSelf(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function bloodline(): Collection
    {
        return $this->ancestors->reverse()->push($this)->merge($this->descendants);
    }

    public function bloodlineAndSelf(): Collection
    {
        return $this->ancestors->reverse()->push($this);
    }

    public function getDepth(): int
    {
        $closureTable = $this->getClosureTable();

        return Cache::remember(
            "{$this->getTable()}.{$this->id}.depth",
            now()->addHours(24),
            fn () => DB::table($closureTable)
                ->where($this->qualifyTreeColumn('ancestor_id', $closureTable), $this->id)
                ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $this->id)
                ->value($this->qualifyTreeColumn('depth', $closureTable)) ?? 0,
        );
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

        $table_name = (new static())->getTable() . '_closure';
        DB::table($table_name)->insert($rows);

        foreach ($model->children as $child) {
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
            $table_name = (new static())->getTable() . '_closure';
            DB::table($table_name)
                ->where('descendant_id', $model->id)
                ->orWhere('ancestor_id', $model->id)
                ->delete();
        });
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
        $table = $table ?? $this->getModelTable();

        return "{$table}.{$column}";
    }

    protected function updateClosureTable(): void
    {
        $closureTable = $this->getClosureTable();

        // Delete old closure entries
        DB::table($closureTable)
            ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $this->id)
            ->delete();

        // Insert self-referential record
        DB::table($closureTable)->insert([
            'ancestor_id' => $this->id,
            'descendant_id' => $this->id,
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert records for all ancestors
        if ($this->parent_id) {
            $ancestors = DB::table($closureTable)
                ->where($this->qualifyTreeColumn('descendant_id', $closureTable), $this->parent_id)
                ->get();

            foreach ($ancestors as $ancestor) {
                DB::table($closureTable)->insert([
                    'ancestor_id' => $ancestor->ancestor_id,
                    'descendant_id' => $this->id,
                    'depth' => $ancestor->depth + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Clear cache
        Cache::forget("{$this->getTable()}.{$this->id}.depth");
    }
}
