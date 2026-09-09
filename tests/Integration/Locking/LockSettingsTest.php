<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Locking\Locked;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\User;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Tests\Stubs\Locking\LockableTestModel;

/**
 * Record locking answers to a per-table switch, the way soft deletes and
 * versioning already do. The trait puts the columns on the table; the
 * `lock_{table}` setting decides whether they are enforced.
 */
beforeEach(function (): void {
    Schema::dropIfExists('lockable_test_models');
    Schema::create('lockable_test_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->unsignedBigInteger('lock_version')->nullable();
        $table->timestamp('locked_at')->nullable();
        $table->unsignedBigInteger('locked_user_id')->nullable();
        $table->timestamp('locked_until')->nullable();
    });

    app(PerModelSettingResolver::class)->flush();
});

function writeLockSetting(string $table, bool $enabled): void
{
    // Written straight to the table: `Setting` carries `HasApprovals`, so a write
    // through the model can land in the approval queue instead of the row, and this
    // test is about the reading side.
    $name = PerModelSettingResolver::nameFor('lock', $table);

    DB::table(CoreTables::Settings->value)->updateOrInsert(
        ['name' => $name],
        [
            'value' => json_encode($enabled),
            'encrypted' => false,
            'type' => SettingTypeEnum::Boolean->value,
            'group_name' => 'locking',
            'description' => "Lock status for {$table}",
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app(PerModelSettingResolver::class)->flushGroup('locking');
    app(PerModelSettingResolver::class)->flushNameIndex();
    app()->forgetInstance(PerModelSettingResolver::class);
}

it('keeps locking on when no setting has been written', function (): void {
    $model = new LockableTestModel;

    expect($model->locksEnabledBySettings())->toBeTrue()
        ->and(new Locked()->usesHasLocks($model))->toBeTrue();
});

it('switches locking off for one table from settings', function (): void {
    writeLockSetting('lockable_test_models', false);

    $model = new LockableTestModel;

    expect($model->locksEnabledBySettings())->toBeFalse()
        ->and(new Locked()->usesHasLocks($model))->toBeFalse()
        ->and(new Locked()->doesNotUseHasLocks($model))->toBeTrue();
});

it('switches it back on', function (): void {
    writeLockSetting('lockable_test_models', false);
    writeLockSetting('lockable_test_models', true);

    expect(new LockableTestModel()->locksEnabledBySettings())->toBeTrue();
});

/**
 * The guard is the point of the switch: with locking off, a record someone else
 * holds is an ordinary record again.
 */
it('lets the write guard through on a record held by somebody else', function (): void {
    $holder = User::factory()->create();
    $record = LockableTestModel::query()->create(['name' => 'doc']);
    $record->lockBy($holder);

    writeLockSetting('lockable_test_models', false);

    $record->fresh()->update(['name' => 'edited']);

    expect(LockableTestModel::query()->findOrFail($record->id)->name)->toBe('edited');
});

/**
 * A model that pins the answer in code is not asking settings anything, which is
 * why the seeder writes no row for it.
 */
it('lets a model override the setting in code', function (): void {
    writeLockSetting('lockable_test_models', true);

    $model = new class extends Model
    {
        use HasLocks;

        protected bool $locksEnabled = false;

        protected $table = 'lockable_test_models';
    };

    expect($model->locksEnabledBySettings())->toBeFalse()
        ->and(new Locked()->usesHasLocks($model))->toBeFalse();
});
