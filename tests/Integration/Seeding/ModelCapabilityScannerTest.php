<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\DynamicEntity;
use Modules\Core\Models\License;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Seeding\ModelCapabilities;
use Modules\Core\Seeding\ModelCapabilityScanner;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;
use Modules\Core\Tests\Stubs\Locking\OptimisticLockModel;
use Modules\Core\Tests\Stubs\Seeding\UnresolvableCapabilityModel;

it('reports HasApprovals without a second filesystem walk', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $tables = array_column($scanned, null, 'table');

    expect($tables)->toHaveKey((new Setting)->getTable())
        ->and($tables[(new Setting)->getTable()]->hasApprovals)->toBeTrue();
});

it('computes the trait set once per model', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $classes = array_column($scanned, 'modelClass');

    expect($classes)->toBe(array_unique($classes));
});

it('reports hasVersions for License, and none of the other five capabilities', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $byClass = array_column($scanned, null, 'modelClass');

    expect($byClass)->toHaveKey(License::class);

    $license = $byClass[License::class];

    expect($license->hasVersions)->toBeTrue()
        ->and($license->hasSoftDeletes)->toBeFalse()
        ->and($license->hasLocks)->toBeFalse()
        ->and($license->hasOptimisticLocking)->toBeFalse()
        ->and($license->hasTranslations)->toBeFalse()
        ->and($license->hasApprovals)->toBeFalse();
});

it('reports hasSoftDeletes and hasLocks for User, not hasOptimisticLocking, hasTranslations or hasApprovals', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $byClass = array_column($scanned, null, 'modelClass');

    expect($byClass)->toHaveKey(User::class);

    $user = $byClass[User::class];

    expect($user->hasSoftDeletes)->toBeTrue()
        ->and($user->hasLocks)->toBeTrue()
        ->and($user->hasOptimisticLocking)->toBeFalse()
        ->and($user->hasTranslations)->toBeFalse()
        ->and($user->hasApprovals)->toBeFalse();
});

/**
 * Scans only the given models, restoring the discovered list afterwards.
 *
 * @param  list<class-string>  $model_classes
 * @return array<class-string, ModelCapabilities>
 */
function scanCapabilitiesOf(array $model_classes): array
{
    $original_active_models = HelpersCache::getModels('active');
    HelpersCache::setModels('active', $model_classes);

    try {
        return array_column(app(ModelCapabilityScanner::class)->scan(), null, 'modelClass');
    } finally {
        if ($original_active_models === null) {
            HelpersCache::clearModels();
        } else {
            HelpersCache::setModels('active', $original_active_models);
        }
    }
}

it('distinguishes hasLocks from hasOptimisticLocking', function (): void {
    $byClass = scanCapabilitiesOf([CronJob::class, OptimisticLockModel::class]);

    // CronJob carries HasLocks but not HasOptimisticLocking: catches a swap
    // between the two constants in either direction.
    expect($byClass[CronJob::class]->hasLocks)->toBeTrue()
        ->and($byClass[CronJob::class]->hasOptimisticLocking)->toBeFalse()
        ->and($byClass[OptimisticLockModel::class]->hasOptimisticLocking)->toBeTrue();
});

it('reports hasTranslations for a translatable model, not hasLocks, hasOptimisticLocking or hasApprovals', function (): void {
    $translatable = scanCapabilitiesOf([FakeTranslatableModel::class])[FakeTranslatableModel::class];

    expect($translatable->hasTranslations)->toBeTrue()
        ->and($translatable->hasLocks)->toBeFalse()
        ->and($translatable->hasOptimisticLocking)->toBeFalse()
        ->and($translatable->hasApprovals)->toBeFalse();
});

it('does not confuse hasOptimisticLocking with hasApprovals', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $byClass = array_column($scanned, null, 'modelClass');

    expect($byClass)->toHaveKey(Setting::class);

    expect($byClass[Setting::class]->hasApprovals)->toBeTrue()
        ->and($byClass[Setting::class]->hasOptimisticLocking)->toBeFalse();
});

it('logs a warning and keeps scanning when a model fails to resolve, instead of skipping silently', function (): void {
    Log::spy();

    $original_active_models = HelpersCache::getModels('active');

    // Inject a deliberately unresolvable model alongside a known-good one:
    // proves the skip is observable AND that one broken model does not stop
    // the rest of the scan.
    HelpersCache::setModels('active', [
        UnresolvableCapabilityModel::class,
        Setting::class,
    ]);

    try {
        $scanned = app(ModelCapabilityScanner::class)->scan();
    } finally {
        if ($original_active_models === null) {
            HelpersCache::clearModels();
        } else {
            HelpersCache::setModels('active', $original_active_models);
        }
    }

    $classes = array_column($scanned, 'modelClass');

    expect($classes)->not->toContain(UnresolvableCapabilityModel::class)
        ->and($classes)->toContain(Setting::class);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Model capability scan skipped a model'
                && ($context['model'] ?? null) === UnresolvableCapabilityModel::class
                && ($context['exception'] ?? null) instanceof Throwable;
        });
});

it('gives DynamicEntity no capabilities, since its table is only a runtime placeholder', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $byClass = array_column($scanned, null, 'modelClass');

    expect($byClass)->toHaveKey(DynamicEntity::class);

    $dynamic = $byClass[DynamicEntity::class];

    expect($dynamic->hasVersions)->toBeFalse()
        ->and($dynamic->hasSoftDeletes)->toBeFalse()
        ->and($dynamic->hasLocks)->toBeFalse()
        ->and($dynamic->hasOptimisticLocking)->toBeFalse()
        ->and($dynamic->hasTranslations)->toBeFalse()
        ->and($dynamic->hasApprovals)->toBeFalse();
});
