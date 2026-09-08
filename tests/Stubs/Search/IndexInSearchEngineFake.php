<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Support\Collection;
use RuntimeException;

final class IndexInSearchEngineFake
{
    public bool $updated = false;

    public bool $received_collection = false;

    public bool $throw_on_update = false;

    /**
     * Mirrors the real Scout engine contract: update() receives a collection of
     * models (and calls ->isEmpty() on it), never a single model.
     */
    public function update(mixed $models): void
    {
        if ($this->throw_on_update) {
            throw new RuntimeException('forced update failure');
        }

        $this->received_collection = $models instanceof Collection;
        $this->updated = true;
    }
}
