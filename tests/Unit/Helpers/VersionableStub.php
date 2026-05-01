<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit\Helpers;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasVersions;
use Overtrue\LaravelVersionable\VersionStrategy;

final class VersionableStub extends Model
{
    use HasVersions;

    protected $table = 'versionables';

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    public function shouldBeVersioning(): bool
    {
        return true;
    }
}
