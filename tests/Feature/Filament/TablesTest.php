<?php

declare(strict_types=1);

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Modules\CMS\Models\Comment;
use Modules\Core\Filament\Resources\Modifications\Tables\ModificationsTable;
use Modules\Core\Filament\Resources\Permissions\Tables\PermissionsTable;
use Modules\Core\Filament\Resources\Settings\Tables\SettingsTable;
use Modules\Core\Filament\Resources\Users\Tables\UsersTable;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

beforeEach(function (): void {
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $admin */
    $admin = App\Models\User::query()->create(User::factory()->raw([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]));

    $admin_role = Role::factory()->create(['name' => 'admin']);
    $admin->roles()->attach($admin_role);

    \Illuminate\Support\Facades\Auth::login($admin);
});

it('builds cached distinct options for permissions table filters', function (): void {
    Permission::factory()->create(['guard_name' => 'web']);
    Permission::factory()->create(['guard_name' => 'api']);

    $method = new ReflectionMethod(PermissionsTable::class, 'cachedDistinctOptions');
    $method->setAccessible(true);
    $options = $method->invoke(null, 'guard_name');

    expect($options)->toHaveKey('web')
        ->and($options)->toHaveKey('api');
});

it('builds cached group options for settings filters', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create(['group_name' => 'base']);
    Setting::factory()->persistedWithoutApprovalCapture()->create(['group_name' => 'security']);

    $method = new ReflectionMethod(SettingsTable::class, 'cachedGroupNameOptions');
    $method->setAccessible(true);
    $options = $method->invoke(null);

    expect($options)->toHaveKey('base')
        ->and($options)->toHaveKey('security');
});

it('applies settings default sort callback', function (): void {
    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => Setting::query());

    SettingsTable::configure($table);
    $query = $table->getDefaultSort(Setting::query(), 'asc');

    $orders = $query->getQuery()->orders ?? [];
    $order_columns = array_values(array_filter(array_map(static fn (array $order): ?string => $order['column'] ?? null, $orders)));

    expect($order_columns)->toContain('group_name')
        ->and($order_columns)->toContain('name');
});

it('evaluates modifications table comment-only columns without a record', function (): void {
    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => Modification::query());

    ModificationsTable::configure($table);

    $columns = $table->getColumns();

    expect($columns['meta']->isVisible())->toBeTrue()
        ->and($columns['disapprovers_required']->isVisible())->toBeTrue();

    $comment_modification = new Modification(['modifiable_type' => Comment::class]);
    $other_modification = new Modification(['modifiable_type' => User::class]);

    expect($columns['meta']->record($comment_modification)->isVisible())->toBeTrue()
        ->and($columns['meta']->record($other_modification)->isVisible())->toBeFalse()
        ->and($columns['disapprovers_required']->record($comment_modification)->isVisible())->toBeTrue()
        ->and($columns['disapprovers_required']->record($other_modification)->isVisible())->toBeFalse();
});

it('executes users table reset password action closure', function (): void {
    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => User::query());

    UsersTable::configure($table);
    $actions = $table->getFlatRecordActions();
    $action = $actions['reset_password'];
    $callback = $action->getActionFunction();

    $sent_reset_to = null;
    $record = \Mockery::mock(App\Models\User::class)->makePartial();
    $record->email = 'reset@example.com';
    $record->shouldReceive('sendPasswordResetNotification')
        ->once()
        ->with('reset@example.com')
        ->andReturnUsing(function ($token) use (&$sent_reset_to): void {
            $sent_reset_to = (string) $token;
        });

    expect($callback)->not->toBeNull();
    $callback($record);

    expect($sent_reset_to)->toBe('reset@example.com');
});

it('configures stacked image overlap to 1 for translations locale', function (): void {
    $translatable_model = new class extends \Illuminate\Database\Eloquent\Model
    {
        use \Modules\Core\Models\Concerns\HasTranslations;

        protected $table = 'test_translatable_models';

        public function getTable(): string
        {
            return 'test_translatable_models';
        }
    };

    $model_class = get_class($translatable_model);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => $model_class::query());

    $resource = new class
    {
        use \Modules\Core\Filament\Utils\HasTable;

        public function configure(Table $table): Table
        {
            return self::configureTable($table);
        }
    };

    $resource->configure($table);

    $columns = $table->getColumns();
    $column = $columns['translations.locale'] ?? null;

    expect($column)->not->toBeNull()
        ->and($column)->toBeInstanceOf(ImageColumn::class)
        ->and($column->getOverlap())->toBe(1);
});
