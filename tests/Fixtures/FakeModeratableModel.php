<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasApprovals;

class FakeModeratableModel extends Model
{
    use HasApprovals;

    protected $table = 'fake_moderatable_models';
}
