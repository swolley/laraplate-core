<?php

declare(strict_types=1);

use BladeUI\Icons\Factory as IconFactory;
use Modules\Core\Providers\CoreServiceProvider;

it('serves the platform icon set under its own prefix', function (): void {
    $svg = app(IconFactory::class)->svg('laraplate-snowflake')->toHtml();

    expect($svg)->toContain('viewBox="0 0 24 24"');
});

it('draws the freeze icon in the same grammar as the Heroicons beside it', function (): void {
    // The panel puts this icon in a column next to lock-closed and lock-open. A filled path or a
    // different box would read as heavier than its neighbours at the size a table cell gives it.
    $ours = file_get_contents(module_path('Core', 'resources/svg/snowflake.svg'));
    $theirs = file_get_contents(base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg/o-lock-closed.svg'));

    foreach (['viewBox="0 0 24 24"', 'fill="none"', 'stroke="currentColor"', 'stroke-width="1.5"'] as $attribute) {
        expect($ours)->toContain($attribute)
            ->and($theirs)->toContain($attribute);
    }
});

it('claims its prefix only once, so registering the provider again does not throw', function (): void {
    // Blade Icons refuses a set whose prefix is already taken, and this provider is registered a
    // second time in the same container by its own test suite.
    $factory = app(IconFactory::class);

    $register = new ReflectionMethod(CoreServiceProvider::class, 'registerIconSet');
    $register->invoke(new CoreServiceProvider(app()));

    expect($factory->svg('laraplate-snowflake')->toHtml())->toContain('viewBox="0 0 24 24"');
});
