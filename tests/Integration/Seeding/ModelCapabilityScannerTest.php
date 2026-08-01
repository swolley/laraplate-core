<?php

declare(strict_types=1);

use Modules\CMS\Models\Content;
use Modules\CMS\Models\Tag;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\License;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Seeding\ModelCapabilityScanner;

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
