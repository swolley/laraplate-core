<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Actions\Settings\GetTranslationsAction;
use Modules\Core\Services\Translation\TranslationCatalogService;
use Tests\TestCase;

final class GetTranslationsActionTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->tmpDir = sys_get_temp_dir() . '/langs-' . bin2hex(random_bytes(5));
        mkdir($this->tmpDir . '/en', 0777, true);
        mkdir($this->tmpDir . '/it', 0777, true);

        file_put_contents($this->tmpDir . '/en/app.php', "<?php return ['foo' => 'bar'];");
        file_put_contents($this->tmpDir . '/it/app.php', "<?php return ['foo' => 'baz'];");
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function test_returns_all_translations(): void
    {
        $service = new TranslationCatalogService(
            filesystem: new Filesystem(),
            languagesProvider: fn () => [$this->tmpDir . '/en', $this->tmpDir . '/it'],
        );

        $action = new GetTranslationsAction($service);

        $result = $action(null);

        $this->assertArrayHasKey('en', $result);
        $this->assertArrayHasKey('it', $result);
        $this->assertSame('bar', $result['en']['app.foo']);
        $this->assertSame('baz', $result['it']['app.foo']);
    }

    public function test_returns_specific_language(): void
    {
        $service = new TranslationCatalogService(
            filesystem: new Filesystem(),
            languagesProvider: fn () => [$this->tmpDir . '/en', $this->tmpDir . '/it'],
        );

        $action = new GetTranslationsAction($service);

        $result = $action('it');

        $this->assertArrayHasKey('app.foo', $result);
        $this->assertSame('baz', $result['app.foo']);
    }
}

