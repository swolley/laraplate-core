<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;

abstract class Model extends BaseModel
{
    use HasFactory;
    use HasValidations;
    use HasVersions;
}
