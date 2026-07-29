<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

final class ModuleDatabaseActivatorAffinityModel extends Model
{
    protected $connection = 'module_activator_affinity';

    protected $table = 'custom_module_settings';
}
