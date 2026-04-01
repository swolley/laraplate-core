<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Auth;

use Illuminate\Auth\MustVerifyEmail;
use Modules\Core\Models\User;

class ProviderUserAliasStub extends User
{
    use MustVerifyEmail;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'email_verified_at',
        'password',
        'lang',
        'social_id',
        'social_service',
        'social_token',
        'social_refresh_token',
        'social_token_secret',
        'license_id',
    ];
}
