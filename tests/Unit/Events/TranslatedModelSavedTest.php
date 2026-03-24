<?php

declare(strict_types=1);

use Modules\Core\Events\TranslatedModelSaved;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('creates event with model and optional locales', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();
    $event = new TranslatedModelSaved($setting, ['en', 'it'], true);

    expect($event->model)->toBe($setting)
        ->and($event->locales)->toBe(['en', 'it'])
        ->and($event->force)->toBeTrue()
        ->and($event->isHandled())->toBeFalse();
});

it('markAsHandled sets handled flag', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();
    $event = new TranslatedModelSaved($setting);

    $event->markAsHandled();

    expect($event->isHandled())->toBeTrue();
});
