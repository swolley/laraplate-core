<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Actions\Docs\MergeSwaggerDocsAction;

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
        $fs->put($tmpDir . '/CMS-swagger.json', json_encode($moduleJson));

        $action = new MergeSwaggerDocsAction(
            filesystem: $fs,
            modulesProvider: static fn () => ['App', 'CMS'],
            pathResolver: static fn (string $module): string => $tmpDir . '/' . $module . '-swagger.json',
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

it('skips modules without swagger documents', function (): void {
    $tmpDir = sys_get_temp_dir() . '/swagger-' . bin2hex(random_bytes(5));
    mkdir($tmpDir, 0777, true);

    try {
        $fs = new Filesystem();
        $fs->put($tmpDir . '/App-swagger.json', json_encode([
            'paths' => [
                '/v1/app-path' => [],
            ],
        ]));

        $action = new MergeSwaggerDocsAction(
            filesystem: $fs,
            modulesProvider: static fn () => ['App', 'MissingModule'],
            pathResolver: static fn (string $module): string => $tmpDir . '/' . $module . '-swagger.json',
        );

        $result = $action('v1');

        expect($result['paths'])->toHaveKey('/v1/app-path');
    } finally {
        (new Filesystem())->deleteDirectory($tmpDir);
    }
});
