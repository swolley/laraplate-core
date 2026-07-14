<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Modules\Core\Search\Engines\DatabaseEngine;

final class DatabaseEngineSQLiteVectorSearchUser extends Model
{
    use Searchable;

    protected $table = 'users';

    protected $guarded = [];

    public function searchableUsing(): DatabaseEngine
    {
        return new DatabaseEngine();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->getKey(),
            'username' => (string) $this->getAttribute('username'),
            'email' => (string) $this->getAttribute('email'),
        ];
    }
}
