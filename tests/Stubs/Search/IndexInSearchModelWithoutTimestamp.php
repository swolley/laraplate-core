<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class IndexInSearchModelWithoutTimestamp extends Model
{
    use Searchable;

    public bool $unsearchable_called = false;

    public bool $should_be_searchable = true;

    public IndexInSearchEngineFake $engine;

    protected $table = 'settings';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->engine = new IndexInSearchEngineFake;
        $this->setAttribute($this->getKeyName(), 1);
    }

    public function searchableAs(): string
    {
        return 'settings';
    }

    public function searchableUsing(): IndexInSearchEngineFake
    {
        return $this->engine;
    }

    public function shouldBeSearchable(): bool
    {
        return $this->should_be_searchable;
    }

    public function unsearchable(): void
    {
        $this->unsearchable_called = true;
    }
}
