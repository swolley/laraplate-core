<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Foundation\Auth\User as BaseUser;

class FakeAuthUser extends BaseUser
{
    protected $fillable = ['name', 'email'];
}

