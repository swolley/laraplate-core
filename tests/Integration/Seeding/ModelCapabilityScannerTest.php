<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Content;
use Modules\CMS\Models\Tag;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\License;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Seeding\ModelCapabilityScanner;
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

it('distinguishes hasLocks from hasOptimisticLocking', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $byClass = array_column($scanned, null, 'modelClass');

    expect($byClass)->toHaveKey(CronJob::class)
        ->and($byClass)->toHaveKey(Content::class);

    // CronJob carries HasLocks but not HasOptimisticLocking: catches a swap
    // between the two constants in either direction.
    expect($byClass[CronJob::class]->hasLocks)->toBeTrue()
        ->and($byClass[CronJob::class]->hasOptimisticLocking)->toBeFalse();

    // Content is the only model in the codebase carrying HasOptimisticLocking,
    // confirming the constant matches a real trait.
    expect($byClass[Content::class]->hasOptimisticLocking)->toBeTrue();
});

it('reports hasTranslations for Tag, not hasLocks, hasOptimisticLocking or hasApprovals', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $byClass = array_column($scanned, null, 'modelClass');

    expect($byClass)->toHaveKey(Tag::class);

    $tag = $byClass[Tag::class];

    expect($tag->hasTranslations)->toBeTrue()
        ->and($tag->hasSoftDeletes)->toBeTrue()
        ->and($tag->hasLocks)->toBeFalse()
        ->and($tag->hasOptimisticLocking)->toBeFalse()
        ->and($tag->hasApprovals)->toBeFalse();
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
