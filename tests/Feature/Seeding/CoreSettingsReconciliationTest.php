<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\ModelCapabilityScanner;
use Modules\Core\Services\PerModelSettingResolver;

it('seeds per-model capability settings with the resolver naming', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $name = PerModelSettingResolver::nameFor('version_strategy', (new Setting)->getTable());

    expect(Setting::query()->withoutGlobalScopes()->where('name', $name)->exists())->toBeTrue();
});

it('stamps a derived setting with the module that owns the model, not Core', function (): void {
    // HasVersions/SoftDeletes are baked into every model via the shared
    // Modules\Core\Overrides\Model base class (see the neighboring test's
    // comment), so any model outside Modules\Core is guaranteed to produce a
    // version_strategy_{table} row. Discover one dynamically instead of
    // hardcoding a module name, so this does not rot if module contents change.
    $foreign = collect(app(ModelCapabilityScanner::class)->scan())
        ->first(fn ($capability): bool => str_starts_with($capability->modelClass, 'Modules\\')
            && ! str_starts_with($capability->modelClass, 'Modules\\Core\\'));

    expect($foreign)->not->toBeNull('Expected at least one non-Core module model to be scannable.');

    $owning_module = explode('\\', $foreign->modelClass)[1];
    $name = PerModelSettingResolver::nameFor('version_strategy', $foreign->table);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $setting = Setting::query()->withoutGlobalScopes()->where('name', $name)->sole();

    expect($setting->module)->toBe($owning_module);
});

it('collapses a table-derived setting shared by several model classes into one row owned by the table\'s declaring class', function (): void {
    // Entity/Taxonomy/Preset/Presettable/User are Core base classes whose
    // `$table` every module extends unchanged, so several concrete leaf
    // classes across CMS/ERP (and App\Models\User) legitimately resolve to
    // the same table — and therefore the same derived setting name. Discover
    // one such table dynamically rather than hardcoding a class, so this
    // does not rot if the set of shared-table models changes.
    $by_table = [];

    foreach (app(ModelCapabilityScanner::class)->scan() as $capability) {
        $by_table[$capability->table][] = $capability->modelClass;
    }

    $shared_table = null;

    foreach ($by_table as $table => $classes) {
        if (count($classes) > 1) {
            $shared_table = $table;

            break;
        }
    }

    expect($shared_table)->not->toBeNull(
        'Expected at least one table shared by multiple model classes (e.g. Entity/Taxonomy/Preset/Presettable/User).',
    );

    // Every currently known shared table is one of these Core base tables, so
    // soft_deletes (true for all of them, unlike version_strategy which the
    // pivot Presettable classes do not have) is guaranteed to be seeded
    // regardless of which shared table was discovered first.
    $name = PerModelSettingResolver::nameFor(CoreDatabaseSeeder::SOFT_DELETES_NAME_PREFIX, (string) $shared_table);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $settings = Setting::query()->withoutGlobalScopes()->where('name', $name)->get();

    expect($settings)->toHaveCount(1)
        ->and($settings->first()->module)->toBe('Core');
});

it('logs a warning naming the setting and the competing model classes when a table-derived setting collides', function (): void {
    Log::spy();

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    Log::shouldHaveReceived('warning')->atLeast()->once()->withArgs(
        fn (string $message, array $context): bool => str_contains($message, 'share a table-derived setting name')
            && isset($context['setting'], $context['module'], $context['model_classes'])
            && is_array($context['model_classes'])
            && count($context['model_classes']) > 1,
    );
});

it('is idempotent and leaves operator values untouched on a second run', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    // Drift the description too, not just the operator value: an unchanged
    // structural row lands in `unchanged` and never reaches the upsert
    // payload at all, so the second run would prove nothing about the
    // $update list. Drifting a structural column forces a real realignment,
    // so this assertion is only satisfied if the operator value genuinely
    // survives that realignment rather than coinciding with it.
    Setting::query()->withoutGlobalScopes()
        ->where('name', 'pagination')
        ->update(['value' => json_encode(999), 'description' => 'drifted description']);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'pagination')->sole();

    expect($setting->value)->toBe(999)
        ->and($setting->description)->toBe('Paginazione default chiamate');
});

it('no longer force-deletes settings during a run', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    // Build a name the reconciliation could genuinely reach: a real,
    // permanently-scanned model's table (Setting itself), for a capability
    // (HasLocks) that model does not have. Setting only uses HasApprovals
    // and HasCache — not HasLocks — so no row in any run's definition set
    // will ever claim this name. This is exactly the shape the old
    // deleteRefuses()/$to_remove_settings pair used to force-delete — it
    // only ever accumulated prefix+table names built from real scanned
    // models missing the corresponding trait. An arbitrary/nonexistent
    // table name would be invisible to both the old and new code alike and
    // would prove nothing.
    //
    // (HasVersions and SoftDeletes are baked into every model via the
    // shared Modules\Core\Overrides\Model base class — see its trait list —
    // so version_strategy_* / soft_deletes_* settings are always claimed
    // and cannot be used to build this scenario.)
    //
    // Setting::query()->forceCreate() silently no-ops in this codebase:
    // Setting::requiresApprovalWhen() shadows HasApprovals and cancels every
    // direct save. Use the factory state that bypasses approval capture so
    // the orphan row is actually persisted.
    $orphan_name = PerModelSettingResolver::nameFor(
        CoreDatabaseSeeder::LOCK_NAME_PREFIX,
        (new Setting)->getTable(),
    );

    $orphan = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => $orphan_name,
        'value' => 'DIFF',
        'encrypted' => false,
        'type' => Modules\Core\Casts\SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'Orphan',
    ]);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $found = Setting::query()->withoutGlobalScopes()->withTrashed()->find($orphan->getKey());

    expect($found)->not->toBeNull()
        ->and($found->trashed())->toBeFalse();
});
