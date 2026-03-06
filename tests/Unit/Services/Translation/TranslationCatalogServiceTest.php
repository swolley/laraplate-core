<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Services\Translation\TranslationCatalogService;
uses(Tests\LaravelTestCase::class);

it('sorts languages with default locale first', function (): void {
    $service = new TranslationCatalogService(
        languagesProvider: fn () => ['/path/de', '/path/en', '/path/it'],
    );

    $languages = $service->getLanguages('en');

    expect($languages)->toBe(['/path/en', '/path/de', '/path/it']);
});

it('mergeLanguageFiles returns dotted keys from php files', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/app.php', "<?php return ['nested' => ['key' => 'value']];");

    try {
        $service = new TranslationCatalogService();
        $result = $service->mergeLanguageFiles($tmpDir);

        expect($result)->toHaveKey('app.nested.key');
        expect($result['app.nested.key'])->toBe('value');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});

it('buildTranslations returns all locales when lang is null', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir . '/en', 0777, true);
    mkdir($tmpDir . '/it', 0777, true);
    file_put_contents($tmpDir . '/en/app.php', "<?php return ['x' => 'en'];");
    file_put_contents($tmpDir . '/it/app.php', "<?php return ['x' => 'it'];");

    try {
        $service = new TranslationCatalogService(
            languagesProvider: fn () => [$tmpDir . '/en', $tmpDir . '/it'],
        );
        $result = $service->buildTranslations(null, 'en');

        expect($result)->toHaveKey('en');
        expect($result)->toHaveKey('it');
        expect($result['en']['app.x'])->toBe('en');
        expect($result['it']['app.x'])->toBe('it');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});

it('buildTranslations returns single flat array when lang is specified', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir . '/en', 0777, true);
    mkdir($tmpDir . '/it', 0777, true);
    file_put_contents($tmpDir . '/en/app.php', "<?php return ['a' => 'en'];");
    file_put_contents($tmpDir . '/it/app.php', "<?php return ['a' => 'it'];");

    try {
        $service = new TranslationCatalogService(
            languagesProvider: fn () => [$tmpDir . '/en', $tmpDir . '/it'],
        );
        $result = $service->buildTranslations('it', 'en');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('app.a');
        expect($result['app.a'])->toBe('it');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});

it('buildTranslations merges non-default locale with default locale', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir . '/en', 0777, true);
    mkdir($tmpDir . '/it', 0777, true);
    file_put_contents($tmpDir . '/en/app.php', "<?php return ['common' => 'base', 'only_en' => 'en'];");
    file_put_contents($tmpDir . '/it/app.php', "<?php return ['common' => 'sovrascritto', 'only_it' => 'it'];");

    try {
        $service = new TranslationCatalogService(
            languagesProvider: fn () => [$tmpDir . '/en', $tmpDir . '/it'],
        );
        $result = $service->buildTranslations(null, 'en');

        expect($result['it']['app.common'])->toBe('sovrascritto');
        expect($result['it']['app.only_en'])->toBe('en');
        expect($result['it']['app.only_it'])->toBe('it');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});

it('buildTranslations treats empty string and zero as all locales', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir . '/en', 0777, true);
    file_put_contents($tmpDir . '/en/app.php', "<?php return ['k' => 'v'];");

    try {
        $service = new TranslationCatalogService(
            languagesProvider: fn () => [$tmpDir . '/en'],
        );

        expect($service->buildTranslations('', 'en'))->toHaveKey('en');
        expect($service->buildTranslations('0', 'en'))->toHaveKey('en');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});

it('buildTranslations skips language when lang filter does not match', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir . '/en', 0777, true);
    mkdir($tmpDir . '/it', 0777, true);
    file_put_contents($tmpDir . '/en/app.php', "<?php return ['x' => 'en'];");
    file_put_contents($tmpDir . '/it/app.php', "<?php return ['x' => 'it'];");

    try {
        $service = new TranslationCatalogService(
            languagesProvider: fn () => [$tmpDir . '/en', $tmpDir . '/it'],
        );
        $result = $service->buildTranslations('it', 'en');

        expect($result)->not->toHaveKey('en');
        expect($result['app.x'])->toBe('it');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});
