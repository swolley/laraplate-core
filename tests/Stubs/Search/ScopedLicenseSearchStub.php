<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasValidity;
use Modules\Core\Search\Engines\DatabaseEngine;
use Modules\Core\Search\Traits\Searchable as CoreSearchable;
use Override;

/**
 * A license searchable through the database engine: its search document holds only real columns, so the
 * engine can query them, and it has the validity scopes declared for filters.
 */
final class ScopedLicenseSearchStub extends Model
{
    use CoreSearchable;
    use HasValidity;

    #[Override]
    protected $table = CoreTables::Licenses->value;

    #[Override]
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
            'id' => $this->getKey(),
            'uuid' => (string) $this->getAttribute('uuid'),
        ];
    }
}
