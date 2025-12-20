<?php

declare(strict_types=1);

use Modules\Core\Actions\Settings\GetSiteConfigsAction;
use Tests\TestCase;

uses(TestCase::class);

it('builds settings array', function (): void {
    $settings = [
        (object) ['name' => 'foo', 'value' => 'bar'],
        (object) ['name' => 'baz', 'value' => 'qux'],
    ];

    $action = new GetSiteConfigsAction(
        settingsProvider: fn () => $settings,
        modulesProvider: static fn () => ['mod1'],
    );

    $result = $action();

    expect($result['foo'])->toBe('bar');
    expect($result['baz'])->toBe('qux');
    expect($result['active_modules'])->toBe(['mod1']);
});
