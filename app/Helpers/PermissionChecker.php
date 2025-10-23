<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
// use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use InvalidArgumentException;

final class PermissionChecker
{
    /**
     * check user permission on requested resource.
     *
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public static function checkPermissions(Request $request, string $entity, ?string $operation = null, ?string $connection = null /* , ?Collection $permissions = null */): bool
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard();
        $guard_name = $guard->name;

        $connection ??= 'default';
        $permission_name = sprintf('%s.%s.%s', $connection, $entity, $operation);

        // if ($permissions instanceof Collection) {
        //     return $permissions->filter(
        //         fn($permission) => $permission->guard === $guard_name && $operation && $operation !== '*'
        //             ? $permission->name === $permission_name
        //             : Str::startsWith($permission->name, $permission_name),
        //     )->isNotEmpty();
        // }

        $user = $request->user();

        if (! $user) {
            $user = Cache::rememberForever('anonymous_user', fn () => user_class()::whereName('anonymous')->first());

            if (! $user) {
                return false;
            }

            Auth::login($user);
            $request->setUserResolver(fn () => $user);
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (bool) $user->hasPermissionTo($permission_name, $guard_name);
    }

    /**
     * ensure user permission on requested resource or throw exception.
     *
     *
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws UnauthorizedException
     */
    public static function ensurePermissions(Request $request, string $entity, ?string $operation = null, ?string $connection = null /* , ?Collection $permissions = null */): true
    {
        throw_unless(self::checkPermissions($request, $entity, $operation, $connection/* , $permissions */), UnauthorizedException::class, 'User not allowed to access this resource');

        return true;
    }
}
