<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit\Helpers;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasVersions;

final class VersionableStub extends Model
{
    use HasVersions;

    protected $table = 'versionables';

    public function shouldBeVersioning(): bool
    {
        return true;
    }
}
