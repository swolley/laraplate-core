<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Actions\Settings\GetTranslationsAction;
use Modules\Core\Services\Translation\TranslationCatalogService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    app()->setLocale('en');
});

it('returns all translations', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir . '/en', 0777, true);
    mkdir($tmpDir . '/it', 0777, true);

    file_put_contents($tmpDir . '/en/app.php', "<?php return ['foo' => 'bar'];");
    file_put_contents($tmpDir . '/it/app.php', "<?php return ['foo' => 'baz'];");

    try {
        $service = new TranslationCatalogService(
            // filesystem: new Filesystem(),
            languagesProvider: fn () => [$tmpDir . '/en', $tmpDir . '/it'],
        );

        $action = new GetTranslationsAction($service);

        $result = $action(null);

        expect($result)->toHaveKey('en');
        expect($result)->toHaveKey('it');
        expect($result['en']['app.foo'])->toBe('bar');
        expect($result['it']['app.foo'])->toBe('baz');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});

it('returns specific language', function (): void {
    $tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
    mkdir($tmpDir . '/en', 0777, true);
    mkdir($tmpDir . '/it', 0777, true);

    file_put_contents($tmpDir . '/en/app.php', "<?php return ['foo' => 'bar'];");
    file_put_contents($tmpDir . '/it/app.php', "<?php return ['foo' => 'baz'];");

    try {
        $service = new TranslationCatalogService(
            // filesystem: new Filesystem(),
            languagesProvider: fn () => [$tmpDir . '/en', $tmpDir . '/it'],
        );

        $action = new GetTranslationsAction($service);

        $result = $action('it');

        expect($result)->toHaveKey('app.foo');
        expect($result['app.foo'])->toBe('baz');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});
