<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\SortableTrait;
use Spatie\EloquentSortable\Sortable;

class SortableStubModel extends Model implements Sortable
{
    use SortableTrait;

    public $timestamps = false;

    protected $table = 'sortable_stub';

    protected $fillable = ['order'];

    protected $casts = [
        'order' => 'integer',
    ];
}
