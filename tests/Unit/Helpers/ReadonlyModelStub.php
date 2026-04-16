<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Helpers\ReadonlyModel;

final class ReadonlyModelStub extends Model
{
    use ReadonlyModel;

    protected $table = 'readonly_model_stubs';

    protected $guarded = [];
}

final class ReadonlySoftDeletingStub extends Model
{
    use ReadonlyModel;
    use SoftDeletes;

    protected $table = 'readonly_soft_deleting_stubs';

    protected $guarded = [];
}
