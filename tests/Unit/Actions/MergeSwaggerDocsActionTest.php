<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Actions\Docs\MergeSwaggerDocsAction;
use Tests\TestCase;

uses(TestCase::class);

it('merges app and module paths', function (): void {
    $tmpDir = sys_get_temp_dir() . '/swagger-' . bin2hex(random_bytes(5));
    mkdir($tmpDir, 0777, true);

    try {
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

        $fs->put($tmpDir . '/App-swagger.json', json_encode($appJson));
        $fs->put($tmpDir . '/Cms-swagger.json', json_encode($moduleJson));

        $action = new MergeSwaggerDocsAction(
            filesystem: $fs,
            modulesProvider: fn () => ['Cms'],
            basePath: $tmpDir,
        );

        $result = $action('v1');

        expect($result)->toHaveKey('paths');
        expect($result['paths'])->toHaveKey('/v1/keep');
        expect($result['paths'])->toHaveKey('/v1/module-path');
        expect($result['paths'])->not->toHaveKey('/api/should-be-removed');
        expect($result['paths'])->not->toHaveKey('/api/module-remove');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});
