<?php

declare(strict_types=1);

use Filament\Actions\CreateAction;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable as HasTableContract;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

final class HasFormHarness
{
    use HasForm;

    public static bool $loaded_permissions = false;

    public static function run(Schema $schema): void
    {
        self::configureForm($schema);
    }

    public static function loadUserPermissionsForTable(User $user): void
    {
        self::$loaded_permissions = $user->exists;
    }
}

final class HasRecordsResourceHarness
{
    public static function getModel(): string
    {
        return Setting::class;
    }
}

class HasRecordsParentHarness
{
    public function __construct(protected Table $table) {}

    /**
     * @return Collection<int, int>|Paginator|CursorPaginator
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        return collect([1, 2, 3]);
    }

    protected static function getResource(): string
    {
        return HasRecordsResourceHarness::class;
    }

    protected function makeTable(): Table
    {
        return $this->table;
    }
}

final class HasRecordsHarness extends HasRecordsParentHarness
{
    use HasRecords;

    public function callHeaderActions(): array
    {
        return $this->getHeaderActions();
    }

    public function callMakeTable(): Table
    {
        return $this->makeTable();
    }
}

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $admin_role = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($admin_role);
    $this->actingAs($this->admin);
});

it('executes HasForm configureForm workflow', function (): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('getModel')->willReturn(User::class);

    HasFormHarness::run($schema);

    expect(HasFormHarness::$loaded_permissions)->toBeTrue();
});

it('returns create action when user can create records', function (): void {
    Gate::define('default.settings.create', static fn (): bool => true);

    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    $actions = $harness->callHeaderActions();

    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toBeInstanceOf(CreateAction::class);
});

it('returns no header actions when user cannot create records', function (): void {
    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    expect($harness->callHeaderActions())->toBe([]);
});

it('shares table fetch duration and returns parent records', function (): void {
    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    $records = $harness->getTableRecords();

    expect($records)->toBeInstanceOf(Collection::class)
        ->and($records->count())->toBe(3)
        ->and(view()->shared('tableFetchDurationSeconds'))->not->toBeNull();
});

it('applies configured groups while building the table', function (): void {
    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    $groups_property = new ReflectionProperty(HasRecordsHarness::class, 'groups');
    $groups_property->setAccessible(true);
    $groups_property->setValue($harness, [Group::make('group_name')]);

    $result_table = $harness->callMakeTable();

    expect($result_table->getGroups())->toHaveCount(1);
});
