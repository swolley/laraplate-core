<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class IndexInSearchEngineFake
{
    public bool $updated = false;

    public bool $throw_on_update = false;

    public function update(Model $model): void
    {
        if ($this->throw_on_update) {
            throw new RuntimeException('forced update failure');
        }

        $this->updated = true;
    }
}
