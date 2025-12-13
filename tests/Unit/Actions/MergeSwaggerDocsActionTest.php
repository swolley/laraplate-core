<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Actions\Docs\MergeSwaggerDocsAction;
use Tests\TestCase;

final class MergeSwaggerDocsActionTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/swagger-' . bin2hex(random_bytes(5));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function test_merges_app_and_module_paths(): void
    {
        $fs = new Filesystem();
        $appJson = [
            'paths' => [
                '/v1/keep' => [],
                '/api/should-be-removed' => [],
            ],
        ];
        $moduleJson = [
            'paths' => [
                '/v1/module-path' => [],
                '/api/module-remove' => [],
            ],
        ];

        $fs->put($this->tmpDir . '/App-swagger.json', json_encode($appJson));
        $fs->put($this->tmpDir . '/Cms-swagger.json', json_encode($moduleJson));

        $action = new MergeSwaggerDocsAction(
            filesystem: $fs,
            modulesProvider: fn () => ['Cms'],
            basePath: $this->tmpDir,
        );

        $result = $action('v1');

        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('/v1/keep', $result['paths']);
        $this->assertArrayHasKey('/v1/module-path', $result['paths']);
        $this->assertArrayNotHasKey('/api/should-be-removed', $result['paths']);
        $this->assertArrayNotHasKey('/api/module-remove', $result['paths']);
    }
}
