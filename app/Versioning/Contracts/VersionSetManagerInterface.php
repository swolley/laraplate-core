<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Contracts;

use Closure;
use Modules\Core\Versioning\ActiveVersionSet;
use Modules\Core\Versioning\Data\VersionSetOptions;
use Modules\Core\Versioning\Data\VersionSetRoot;

interface VersionSetManagerInterface
{
    public function run(
        VersionSetRoot $root,
        Closure $operation,
        ?VersionSetOptions $options = null,
    ): mixed;

    public function enlist(string $connection): void;

    public function current(): ?ActiveVersionSet;
}
