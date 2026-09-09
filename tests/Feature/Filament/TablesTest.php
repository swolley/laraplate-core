<?php

declare(strict_types=1);

use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\CMS\Models\Comment;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Filament\Resources\ACLS\ACLResource;
use Modules\Core\Filament\Resources\ACLS\Tables\ACLsTable;
use Modules\Core\Filament\Resources\Modifications\Tables\ModificationsTable;
use Modules\Core\Filament\Resources\Permissions\Tables\PermissionsTable;
use Modules\Core\Filament\Resources\Settings\Tables\SettingsTable;
use Modules\Core\Filament\Resources\Users\Tables\UsersTable;
use Modules\Core\Filament\Utils\HasTable as HasTableTrait;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\ValidityStubModel;

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

    Illuminate\Support\Facades\Auth::login($admin);
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
    $record = Mockery::mock(App\Models\User::class)->makePartial();
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
    $translatable_model = new class extends Illuminate\Database\Eloquent\Model
    {
        use Modules\Core\Models\Concerns\HasTranslations;

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
        use Modules\Core\Filament\Utils\HasTable;

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

it('renders the acl filters column as a readable expression', function (): void {
    $permission = Permission::factory()->create(['name' => 'default.acl_table_' . uniqid() . '.select']);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => new FiltersGroup([
            new Filter('status', 'published', FilterOperator::Equals),
            new FiltersGroup([
                new Filter('country', ['IT', 'DE'], FilterOperator::In),
                new Filter('archived', false, FilterOperator::Equals),
            ], WhereClause::Or),
        ]),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => ACL::query());

    ACLsTable::configure($table);

    $column = $table->getColumns()['filters']->record($acl->fresh());

    expect($column->formatState($column->getState()))
        ->toBe('status = published and (country in ["IT","DE"] or archived = false)')
        ->and($column->toEmbeddedHtml())->toContain('status = published');
});

it('renders the acl filters column when no filters are stored', function (): void {
    $column = ACLsTable::describeFilters(null);

    expect($column)->toBe('')
        ->and(ACLsTable::describeFilters(new FiltersGroup))->toBe('');
});

it('orders the acl list by priority instead of the json sort column', function (): void {
    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => ACL::query());

    ACLResource::table($table);

    expect($table->getDefaultSortColumn())->toBe('priority')
        ->and($table->getDefaultSortDirection())->toBe('desc');
});

it('renders validity column html through filament when valid_from is set', function (): void {
    $valid_from = now()->startOfSecond();
    $record = new ValidityStubModel(['name' => 'published', 'valid_from' => $valid_from, 'valid_to' => null]);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => ValidityStubModel::query());
    $table->pushColumns([
        TextColumn::make('validity')
            ->getStateUsing(static fn (ValidityStubModel $record): string => HasTableTrait::formatValidityColumnState($record))
            ->html(),
    ]);

    $column = $table->getColumns()['validity']->record($record);

    expect($column->toEmbeddedHtml())
        ->toContain('Valid from:')
        ->toContain($valid_from->format('Y-m-d H:i:s'))
        ->not->toContain('Valid until:');
});

it('renders validity column rows only when dates are present', function (): void {
    $valid_from = now()->startOfSecond();
    $valid_to = now()->addWeek()->startOfSecond();

    $draft = new ValidityStubModel(['name' => 'draft', 'valid_from' => null, 'valid_to' => null]);
    $open_ended = new ValidityStubModel(['name' => 'open', 'valid_from' => $valid_from, 'valid_to' => null]);
    $bounded = new ValidityStubModel(['name' => 'bounded', 'valid_from' => $valid_from, 'valid_to' => $valid_to]);

    expect(HasTableTrait::formatValidityColumnState($draft))->toBe('')
        ->and(HasTableTrait::formatValidityColumnState($open_ended))
        ->toContain('Valid from:')
        ->toContain($valid_from->format('Y-m-d H:i:s'))
        ->not->toContain('Valid until:')
        ->and(HasTableTrait::formatValidityColumnState($bounded))
        ->toContain('Valid from:')
        ->toContain('Valid until:')
        ->toContain($valid_to->format('Y-m-d H:i:s'));
});

it('renders deleted timestamp row only when deleted_at is set', function (): void {
    $created_at = now()->subDay()->startOfSecond();
    $updated_at = now()->subHour()->startOfSecond();
    $deleted_at = now()->startOfSecond();

    $active = new User;
    $active->forceFill([
        'name' => 'Active User',
        'email' => 'active-' . uniqid() . '@example.com',
        'password' => 'Aa1!FilamentAdminPass',
        'created_at' => $created_at,
        'updated_at' => $updated_at,
        'deleted_at' => null,
    ]);

    $deleted = new User;
    $deleted->forceFill([
        'name' => 'Deleted User',
        'email' => 'deleted-' . uniqid() . '@example.com',
        'password' => 'Aa1!FilamentAdminPass',
        'created_at' => $created_at,
        'updated_at' => $updated_at,
        'deleted_at' => $deleted_at,
    ]);

    $active_html = HasTableTrait::formatTimestampsColumnState($active, hasSoftDeletes: true);
    $deleted_html = HasTableTrait::formatTimestampsColumnState($deleted, hasSoftDeletes: true);

    expect($active_html)
        ->toContain('Created:')
        ->toContain('Updated:')
        ->not->toContain('Deleted:')
        ->and($deleted_html)
        ->toContain('Deleted:')
        ->toContain($deleted_at->format('Y-m-d H:i:s'));
});
