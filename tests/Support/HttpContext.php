<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Mockery;
use Mockery\MockInterface;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Approvals never apply to console writes, and the whole suite runs in the console.
 *
 * A test that exercises capture has to pretend an HTTP request, which means swapping the
 * container for a partial mock whose runningInConsole() answers false. Lives here rather
 * than in a module's Pest.php because the approvals tests of Core, CMS and AI all need it,
 * and PSR-4 under Modules\Core\Tests\ reaches every suite through the merged autoload-dev.
 */
final class HttpContext
{
    /**
     * Make App::runningInConsole() answer false for the rest of the test. Calling it again in
     * the same test does nothing: the container is already the partial mock.
     */
    public static function pretendHttpRequest(): void
    {
        if (App::getFacadeRoot() instanceof MockInterface) {
            return;
        }

        $mock = Mockery::mock(App::getFacadeRoot())->makePartial();
        $mock->shouldReceive('runningInConsole')->andReturn(false);

        App::swap($mock);
    }

    /**
     * Log in, on the admin panel, a user who may run the given actions on the model's table but
     * not approve them, inside a pretended HTTP request: every write they make is captured.
     *
     * @param  list<string>  $actions
     */
    public static function panelActorWithoutApproval(Model $model, array $actions = ['select', 'update', 'delete']): User
    {
        if (! class_exists(\App\Models\User::class)) {
            class_alias(User::class, \App\Models\User::class);
        }

        /** @var \App\Models\User $actor */
        $actor = \App\Models\User::query()->create(User::factory()->raw());
        $actor->assignRole(Role::findOrCreate(config('permission.roles.admin'), 'web'));

        foreach ($actions as $action) {
            $permission = PermissionName::forModel($model, $action);
            Permission::findOrCreate($permission, 'web');
            $actor->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        test()->actingAs($actor);
        Filament::setCurrentPanel('admin');
        self::pretendHttpRequest();

        return $actor;
    }
}
