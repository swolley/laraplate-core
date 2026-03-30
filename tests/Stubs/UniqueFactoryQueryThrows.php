<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use RuntimeException;

final class UniqueFactoryQueryThrows extends Model
{
    protected $table = 'users';

    #[Override]
    public static function query(): Builder
    {
        throw new RuntimeException('forced uniqueness query failure');
    }
}
