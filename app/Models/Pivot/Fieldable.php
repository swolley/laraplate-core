<?php

declare(strict_types=1);

namespace Modules\Core\Models\Pivot;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SortableTrait;
use Modules\Core\Observers\FieldableObserver;
use Override;
use Spatie\EloquentSortable\Sortable;

/**
 * @mixin IdeHelperFieldable
 */
#[ObservedBy(FieldableObserver::class)]
final class Fieldable extends Pivot implements Sortable
{
    use HasFactory;
    use HasVersions;
    use SortableTrait;

    #[Override]
    public $incrementing = true;

    #[Override]
    protected $table = 'fieldables';

    #[Override]
    protected $attributes = [
        'is_required' => false,
        'order_column' => 0,
    ];

    #[Override]
    protected $hidden = [
        'created_at',
        'updated_at',
        'field_id',
        'preset_id',
        'order_column',
    ];

    private array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('order_column', 'asc');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'order_column' => 'integer',
            'default' => 'json',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
