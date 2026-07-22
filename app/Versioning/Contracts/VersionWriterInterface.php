<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Contracts;

use Modules\Core\Models\Version;
use Modules\Core\Versioning\Data\VersionChange;

interface VersionWriterInterface
{
    public function write(VersionChange $change): Version;
}
