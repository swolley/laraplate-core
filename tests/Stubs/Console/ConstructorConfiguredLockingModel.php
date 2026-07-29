<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Database\Eloquent\Model;

final class ConstructorConfiguredLockingModel extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection('locking_refresh_constructor_connection');
        $this->setTable('locking_refresh_constructor_table');
    }

    public function lockVersionColumn(): string
    {
        return 'lock_version';
    }

    public function getLockedAtColumn(): string
    {
        return 'locked_at';
    }

    public function getLockedByColumn(): string
    {
        return 'locked_by';
    }
}
