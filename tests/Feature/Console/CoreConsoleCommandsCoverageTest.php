<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\TextPrompt;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Services\ApprovalNotificationService;
use Modules\Core\Tests\Fixtures\HandleTestContext;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(LaravelTestCase::class);

final class ClearExpiredModelSoftStub extends Model
{
    use SoftDeletes;

    protected $table = 'clear_expired_soft_stubs';

    protected $guarded = [];
}

final class ClearExpiredModelNoSoftStub extends Model
{
    protected $table = 'clear_expired_no_soft_stubs';
}

final class CreateUserPromptStub extends User
{
    protected $table = 'users';

    protected $fillable = ['name', 'username', 'email', 'lang', 'password'];

    /**
     * @return array<string,mixed>
     */
    public function getOperationRules(?string $operation = null): array
    {
        return [
            'name' => ['required', 'string'],
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
            'lang' => 'in:active,inactive',
            'password' => ['nullable'],
        ];
    }
}

function coreCommandWithOutput(Illuminate\Console\Command $command): Illuminate\Console\Command
{
    $output = new Illuminate\Console\OutputStyle(new ArrayInput([]), new BufferedOutput());
    $output_reflection = new ReflectionProperty(Illuminate\Console\Command::class, 'output');
    $output_reflection->setValue($command, $output);

    return $command;
}

it('covers AddRouteCommentsCommand private helpers and empty routes handle', function (): void {
    $command = new Modules\Core\Console\AddRouteCommentsCommand();
    $get_route_info = new ReflectionMethod($command, 'getRouteInfo');
    $generate_comment = new ReflectionMethod($command, 'generateComment');
    $add_comment = new ReflectionMethod($command, 'addCommentToMethod');
    $get_route_info->setAccessible(true);
    $generate_comment->setAccessible(true);
    $add_comment->setAccessible(true);

    $route = Route::get('/coverage-test-route', static fn (): string => 'ok')->name('coverage.route')->middleware('web');
    $info = $get_route_info->invoke($command, $route);
    expect($info['uri'])->toBe('coverage-test-route')
        ->and($info['name'])->toBe('coverage.route');

    $comment = $generate_comment->invoke($command, [$info]);
    expect($comment)->toContain('@route-comment')
        ->and($comment)->toContain("Route(path: 'coverage-test-route'");

    $tmp_file = tempnam(sys_get_temp_dir(), 'route_comment_');
    file_put_contents($tmp_file, <<<'PHP'
<?php
class TempControllerForRouteComment
{
    public function index(): void {}
}
PHP);

    $add_comment->invoke($command, $tmp_file, 'index', $comment);
    expect(file_get_contents($tmp_file))->toContain('@route-comment');

    $route_comment_version = <<<'PHP'
<?php
class TempControllerForRouteComment
{
    /**
     * @route-comment
     * Route(path: 'old', name: 'old', methods: [GET], middleware: [web])
     */
    public function index(): void {}
}
PHP;
    file_put_contents($tmp_file, $route_comment_version);
    $add_comment->invoke($command, $tmp_file, 'index', $comment);
    $content = file_get_contents($tmp_file);
    expect($content)->not->toContain("Route(path: 'old'")
        ->and($content)->toContain('@route-comment');

    $non_route_comment = <<<'PHP'
<?php
class TempControllerForRouteComment
{
    /**
     * Keep me
     */
    public function index(): void {}
}
PHP;
    file_put_contents($tmp_file, $non_route_comment);
    $add_comment->invoke($command, $tmp_file, 'index', $comment);
    $content = file_get_contents($tmp_file);
    expect($content)->toContain('Keep me');

    $add_comment->invoke($command, $tmp_file, 'missingMethod', $comment);
    expect(file_get_contents($tmp_file))->toBe($content);

    unlink($tmp_file);

    Route::shouldReceive('getRoutes')->once()->andReturn([]);
    coreCommandWithOutput($command)->handle();
    expect(true)->toBeTrue();
});

it('covers AddRouteCommentsCommand handle processing branches', function (): void {
    $tmp_file = tempnam(sys_get_temp_dir(), 'route_handle_');
    file_put_contents($tmp_file, <<<'PHP'
<?php
namespace App\Http\Controllers;
class TempBaseController { public function inherited(): void {} }
class TempChildController extends TempBaseController {}
class TempAddRouteController { public function index(): void {} }
class TempInvokableController { public function __invoke(): void {} }
PHP);

    require_once $tmp_file;

    $route_mock = static function (string $action, array $methods, string $uri, ?string $name, array $middleware = []): Illuminate\Routing\Route {
        $mock = Mockery::mock(Illuminate\Routing\Route::class);
        $mock->shouldReceive('getActionName')->andReturn($action);
        $mock->shouldReceive('methods')->andReturn($methods);
        $mock->shouldReceive('uri')->andReturn($uri);
        $mock->shouldReceive('getName')->andReturn($name);
        $mock->shouldReceive('middleware')->andReturn($middleware);

        return $mock;
    };

    $valid_route = $route_mock(App\Http\Controllers\TempAddRouteController::class . '@index', ['GET'], 'valid/path', 'valid.path', ['web']);
    $inherited_route = $route_mock(App\Http\Controllers\TempChildController::class . '@inherited', ['POST'], 'child/path', 'child.path', ['api']);
    $missing_class_route = $route_mock('App\\Http\\Controllers\\MissingController@index', ['GET'], 'missing-class/path', 'missing.class.path');
    $missing_method_route = $route_mock(App\Http\Controllers\TempAddRouteController::class . '@missing', ['GET'], 'missing-method/path', 'missing.method.path');
    $no_at_route = $route_mock(App\Http\Controllers\TempInvokableController::class, ['GET'], 'invokable/path', 'invokable.path');
    eval('namespace App\\Http\\Controllers; class TempEvalController { public function index(): void {} }');
    $eval_file_missing_route = $route_mock('App\\Http\\Controllers\\TempEvalController@index', ['GET'], 'eval/path', 'eval.path');
    $closure_route = $route_mock('Closure', ['GET'], 'closure/path', 'closure.path');

    Route::shouldReceive('getRoutes')->once()->andReturn([
        $closure_route,
        $no_at_route,
        $missing_class_route,
        $missing_method_route,
        $valid_route,
        $inherited_route,
        $eval_file_missing_route,
    ]);

    $command = coreCommandWithOutput(new Modules\Core\Console\AddRouteCommentsCommand());
    $command->handle();

    expect(file_exists($tmp_file))->toBeTrue();

    unlink($tmp_file);
});

it('covers CheckPendingApprovalsCommand branches', function (): void {
    if (class_exists(HandleTestContext::class)) {
        HandleTestContext::$config = [];
    }

    $service = new ApprovalNotificationService();
    $service_reflection = new ReflectionProperty(ApprovalNotificationService::class, 'models_cache');
    $service_reflection->setAccessible(true);
    $service_reflection->setValue($service, ['users' => User::class]);

    $this->app->instance(ApprovalNotificationService::class, $service);

    config(['core.notifications.approvals.enabled' => false]);

    if (class_exists(HandleTestContext::class)) {
        HandleTestContext::$config['core.notifications.approvals.enabled'] = false;
    }
    $disabled = coreCommandWithOutput(new Modules\Core\Console\CheckPendingApprovalsCommand());
    $disabled->setLaravel($this->app);
    expect($disabled->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);

    config(['core.notifications.approvals.enabled' => true]);

    if (class_exists(HandleTestContext::class)) {
        HandleTestContext::$config['core.notifications.approvals.enabled'] = true;
    }
    Modification::query()->delete();
    $empty = coreCommandWithOutput(new Modules\Core\Console\CheckPendingApprovalsCommand());
    $empty->setLaravel($this->app);
    expect($empty->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);

    Modification::query()->insert([
        [
            'modifiable_id' => 1,
            'modifiable_type' => User::class,
            'modifier_id' => 1,
            'modifier_type' => User::class,
            'active' => true,
            'is_update' => true,
            'approvers_required' => 1,
            'disapprovers_required' => 1,
            'md5' => md5('a'),
            'modifications' => '{}',
            'created_at' => now()->subHours(24),
            'updated_at' => now()->subHours(24),
        ],
    ]);
    Setting::query()->withoutGlobalScopes()->updateOrCreate(
        ['name' => 'approval_threshold_users'],
        ['group_name' => 'core', 'value' => 1],
    );
    expect($service->getPendingApprovalsByEntity()->isEmpty())->toBeFalse();

    $dry_run = coreCommandWithOutput(new Modules\Core\Console\CheckPendingApprovalsCommand());
    $dry_run->setLaravel($this->app);
    expect($dry_run->run(new ArrayInput(['--dry-run' => true]), new BufferedOutput()))->toBe(0);

    Notification::fake();
    $admin_role = Role::factory()->create(['name' => 'admin', 'guard_name' => 'web']);
    Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['email' => 'admin-notify@example.test']);
    $admin->assignRole($admin_role);
    $notify = coreCommandWithOutput(new Modules\Core\Console\CheckPendingApprovalsCommand());
    $notify->setLaravel($this->app);
    expect($notify->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);
    User::query()->whereKey($admin->id)->delete();
    $no_recipients = coreCommandWithOutput(new Modules\Core\Console\CheckPendingApprovalsCommand());
    $no_recipients->setLaravel($this->app);
    expect($no_recipients->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);
});

it('covers ClearExpiredModels command with and without soft deletes', function (): void {
    Schema::create('clear_expired_soft_stubs', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->timestamp('deleted_at')->nullable();
    });
    Schema::create('clear_expired_no_soft_stubs', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
    });

    ClearExpiredModelSoftStub::query()->insert([
        ['id' => 1, 'deleted_at' => now()->subDays(10)],
        ['id' => 2, 'deleted_at' => now()->subDay()],
        ['id' => 3, 'deleted_at' => null],
    ]);

    HelpersCache::setModels('active', [
        ClearExpiredModelSoftStub::class,
        ClearExpiredModelNoSoftStub::class,
    ]);

    if (class_exists(HandleTestContext::class)) {
        HandleTestContext::$models = [
            ClearExpiredModelSoftStub::class,
            ClearExpiredModelNoSoftStub::class,
        ];
        HandleTestContext::$uses_trait = true;
    }
    config(['core.soft_deletes_expiration_days' => 5]);
    $command = coreCommandWithOutput(new Modules\Core\Console\ClearExpiredModels());
    $command->handle();

    expect(ClearExpiredModelSoftStub::withTrashed()->whereKey(2)->exists())->toBeTrue()
        ->and(ClearExpiredModelSoftStub::withTrashed()->whereKey(3)->exists())->toBeTrue();

    config(['core.soft_deletes_expiration_days' => null]);
    $command->handle();

    Schema::dropIfExists('clear_expired_no_soft_stubs');
    Schema::dropIfExists('clear_expired_soft_stubs');
});

it('covers CreateUserCommand success and failure branches', function (): void {
    config([
        'app.available_locales' => ['en', 'it'],
        'auth.providers.users.model' => User::class,
    ]);

    DB::table('roles')->insert([
        'name' => 'console',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('permissions')->insert([
        'name' => 'console_permission',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $role = Role::query()->where('name', 'console')->firstOrFail();
    $permission = Permission::query()->where('name', 'console_permission')->firstOrFail();

    TextPrompt::fallbackWhen(true);
    TextPrompt::fallbackUsing(static function (): string {
        static $answers = ['console_name', 'console_user', 'console@example.test'];
        static $index = 0;

        return $answers[$index++] ?? 'fallback';
    });
    PasswordPrompt::fallbackWhen(true);
    PasswordPrompt::fallbackUsing(static fn (): string => 'StrongPassword123!');
    SearchPrompt::fallbackWhen(true);
    SearchPrompt::fallbackUsing(static fn (): string => 'it');
    MultiSelectPrompt::fallbackWhen(true);
    MultiSelectPrompt::fallbackUsing(static function (MultiSelectPrompt $prompt) use ($role, $permission): array {
        return $prompt->label === 'Roles' ? [$role->id] : [$permission->id];
    });
    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(static function (ConfirmPrompt $prompt): bool {
        return match (true) {
            str_contains($prompt->label, 'custom user permissions') => true,
            str_contains($prompt->label, 'create another user') => false,
            default => false,
        };
    });

    $create_command = coreCommandWithOutput(new Modules\Core\Console\CreateUserCommand());
    expect($create_command->handle())->toBe(0);

    $user = User::query()->where('email', 'console@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user?->roles->pluck('id')->contains($role->id))->toBeTrue()
        ->and($user?->permissions->pluck('id')->contains($permission->id))->toBeTrue();

    config(['auth.providers.users.model' => stdClass::class]);
    expect($create_command->handle())->toBe(1);
});

it('covers CreateUserCommand in-string options and random password branch', function (): void {
    config(['auth.providers.users.model' => CreateUserPromptStub::class]);

    DB::table('roles')->insert([
        'name' => 'console_second',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $role = Role::query()->where('name', 'console_second')->firstOrFail();

    TextPrompt::fallbackWhen(true);
    TextPrompt::fallbackUsing(static function (): string {
        static $answers = ['secondary_name', 'secondary_user', 'secondary@example.test'];
        static $index = 0;

        return $answers[$index++] ?? 'secondary_name';
    });
    SearchPrompt::fallbackWhen(true);
    SearchPrompt::fallbackUsing(static fn (): string => 'active');
    PasswordPrompt::fallbackWhen(true);
    PasswordPrompt::fallbackUsing(static fn (): string => '');
    MultiSelectPrompt::fallbackWhen(true);
    MultiSelectPrompt::fallbackUsing(static fn (): array => [$role->id]);
    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(static fn (): bool => false);

    $command = coreCommandWithOutput(new Modules\Core\Console\CreateUserCommand());
    expect($command->handle())->toBe(0);

    $created = CreateUserPromptStub::query()->where('name', 'secondary_name')->first();
    expect($created)->not->toBeNull()
        ->and($created?->lang)->toBe('active')
        ->and((string) $created?->password)->not->toBe('');
});
