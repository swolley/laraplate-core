<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\CronJobs\CronJobResource;
use Modules\Core\Filament\Resources\CronJobs\Pages\CreateCronJob;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

it('defines Filament pages for cron jobs', function (): void {
    $pages = CronJobResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('cron job resource has required form fields', function (): void {
    $schema = CronJobResource::form(new Schema());
    $components = $schema->getComponents();

    $names = array_map(static fn ($c): ?string => method_exists($c, 'getName') ? $c->getName() : null, $components);

    expect($names)->toContain('name')
        ->and($names)->toContain('command')
        ->and($names)->toContain('schedule')
        ->and($names)->toContain('description')
        ->and($names)->toContain('is_active');
});

it('cron job resource edits the scheduling flags, on by default, and the parameters', function (): void {
    $components = collect(CronJobResource::form(new Schema())->getComponents())
        ->filter(static fn ($component): bool => method_exists($component, 'getName'))
        ->keyBy(static fn ($component): string => $component->getName());

    expect($components->keys()->all())->toContain('on_one_server', 'without_overlapping', 'parameters')
        ->and($components['on_one_server']->getDefaultState())->toBeTrue()
        ->and($components['without_overlapping']->getDefaultState())->toBeTrue();
});

it('cron job resource has no field for a column that the table does not have', function (): void {
    $names = collect(CronJobResource::form(new Schema())->getComponents())
        ->map(static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    expect($names->intersect(['timeout', 'tries', 'retry_after'])->all())->toBe([]);
});

it('creates a cron job from the panel with its parameters as a list and the flags it was given', function (): void {
    HttpContext::panelActorWithoutApproval(new CronJob, ['select', 'insert']);

    Livewire::test(CreateCronJob::class)
        ->fillForm([
            'name' => 'prune-probe',
            'command' => 'model:prune',
            'parameters' => ['--model=Modules\\AI\\Models\\ContextualSuggestion', '--force'],
            'schedule' => '0 3 * * *',
            'on_one_server' => false,
            'without_overlapping' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cron = CronJob::query()->where('name', 'prune-probe')->firstOrFail();

    expect($cron->parameters)->toBe(['--model=Modules\\AI\\Models\\ContextualSuggestion', '--force'])
        ->and($cron->on_one_server)->toBeFalse()
        ->and($cron->without_overlapping)->toBeTrue();
});

it('refuses a command that carries arguments in the panel', function (): void {
    HttpContext::panelActorWithoutApproval(new CronJob, ['select', 'insert']);

    Livewire::test(CreateCronJob::class)
        ->fillForm(['name' => 'prune-probe', 'command' => 'model:prune --force', 'schedule' => '0 3 * * *'])
        ->call('create')
        ->assertHasFormErrors(['command']);
});

it('refuses a parameter that would be run by the shell in the panel', function (): void {
    HttpContext::panelActorWithoutApproval(new CronJob, ['select', 'insert']);

    Livewire::test(CreateCronJob::class)
        ->fillForm(['name' => 'prune-probe', 'command' => 'model:prune', 'parameters' => ['--force; rm -rf /'], 'schedule' => '0 3 * * *'])
        ->call('create')
        ->assertHasFormErrors(['parameters']);
});

it('cron job resource has required table columns', function (): void {
    test()->markTestSkipped('Table column configuration is exercised at app level with full Filament panel wiring.');
});

it('cron job resource has required actions', function (): void {
    test()->markTestSkipped('Table actions configuration is exercised at app level with full Filament panel wiring.');
});
