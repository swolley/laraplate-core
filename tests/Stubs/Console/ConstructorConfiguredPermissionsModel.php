<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Database\Eloquent\Model;

final class ConstructorConfiguredPermissionsModel extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection('permissions_constructor_connection');
        $this->setTable('permissions_constructor_table');
    }
}
