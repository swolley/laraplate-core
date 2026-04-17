<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Models;

use Modules\Core\Overrides\Model;

/**
 * Eloquent model without HasVersions (for console command validation tests).
 */
final class PlainModelWithoutVersions extends Model
{
    protected $table = 'plain_model_without_versions';
}
