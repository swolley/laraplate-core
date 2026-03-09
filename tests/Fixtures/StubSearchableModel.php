<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * Minimal model that uses Searchable so IndexInSearchJob constructor accepts it.
 * Used only to cover the listener dispatch branch.
 */
final class StubSearchableModel extends Model
{
    use Searchable;

    protected $table = 'settings';

    protected $guarded = [];

    public $incrementing = true;

    public function getKey()
    {
        return 1;
    }
}