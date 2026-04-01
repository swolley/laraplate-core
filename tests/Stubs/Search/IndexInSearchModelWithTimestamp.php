<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

final class IndexInSearchModelWithTimestamp extends IndexInSearchModelWithoutTimestamp
{
    public bool $timestamp_updated = false;

    public function updateSearchIndexTimestamp(): void
    {
        $this->timestamp_updated = true;
    }
}
