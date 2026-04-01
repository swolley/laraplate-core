<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Console\VendorPublishCommand;
use Symfony\Component\Console\Input\ArrayInput;

it('resolves vendor publish module migration and config targets via reflection', function (): void {
    $files = new Filesystem();
    $command = new VendorPublishCommand($files);

    $migrations_subpath = config('modules.paths.generator.migration.path');
    $configs_subpath = config('modules.paths.generator.config.path');

    $vendor_root = sys_get_temp_dir() . '/vendor_publish_cov_' . uniqid('', false);
    $migration_dir = $vendor_root . DIRECTORY_SEPARATOR . $migrations_subpath;
    $config_dir = $vendor_root . DIRECTORY_SEPARATOR . $configs_subpath;
    mkdir($migration_dir, 0755, true);
    mkdir($config_dir, 0755, true);

    $migration_name = '2024_01_01_000000_create_publish_cov_table.php';
    $migration_abs = $migration_dir . DIRECTORY_SEPARATOR . $migration_name;
    file_put_contents($migration_abs, '<?php return [];');

    $config_name = 'publish_cov.php';
    $config_abs = $config_dir . DIRECTORY_SEPARATOR . $config_name;
    file_put_contents($config_abs, '<?php return ["k" => 1];');

    $modules = [
        'PublishCov' => [
            'path' => $vendor_root,
            'migrations' => [$migration_abs],
            'config' => [$config_abs],
        ],
    ];

    $prop = new ReflectionProperty(VendorPublishCommand::class, 'modules');
    $prop->setAccessible(true);
    $prop->setValue($command, $modules);

    $migration_exists = new ReflectionMethod(VendorPublishCommand::class, 'moduleMigrationExists');
    $migration_exists->setAccessible(true);
    expect($migration_exists->invoke($command, $migration_name))->toBe($migration_abs);

    $config_exists = new ReflectionMethod(VendorPublishCommand::class, 'moduleConfigExists');
    $config_exists->setAccessible(true);
    expect($config_exists->invoke($command, $config_name))->toBe($config_abs);

    @unlink($migration_abs);
    @unlink($config_abs);
    @rmdir($migration_dir);
    @rmdir($config_dir);
    @rmdir($vendor_root);
});

it('covers publishFile force merge branch for module config', function (): void {
    $files = new Filesystem();
    $command = new VendorPublishCommand($files);

    $configs_subpath = config('modules.paths.generator.config.path');
    $root = sys_get_temp_dir() . '/vendor_publish_force_' . uniqid('', false);
    $config_dir = $root . DIRECTORY_SEPARATOR . $configs_subpath;
    $source_dir = $root . DIRECTORY_SEPARATOR . 'vendor_source' . DIRECTORY_SEPARATOR . $configs_subpath;
    mkdir($config_dir, 0755, true);
    mkdir($source_dir, 0755, true);

    $source_from = $source_dir . DIRECTORY_SEPARATOR . 'published.php';
    $found_in_module = $config_dir . DIRECTORY_SEPARATOR . 'published.php';
    $target_to = $root . DIRECTORY_SEPARATOR . 'published.php';
    file_put_contents($source_from, "<?php return ['from' => 1];");
    file_put_contents($found_in_module, "<?php return ['existing' => 2];");

    $prop = new ReflectionProperty(VendorPublishCommand::class, 'modules');
    $prop->setAccessible(true);
    $prop->setValue($command, [
        'PublishCov' => [
            'path' => $root,
            'migrations' => [],
            'config' => [$found_in_module],
        ],
    ]);

    $components = new class
    {
        public function task(string $message): void {}

        public function twoColumnDetail(string $left, string $right): void {}
    };
    $components_prop = new ReflectionProperty(Illuminate\Console\Command::class, 'components');
    $components_prop->setAccessible(true);
    $components_prop->setValue($command, $components);

    $input_prop = new ReflectionProperty(Illuminate\Console\Command::class, 'input');
    $input_prop->setAccessible(true);
    $input_prop->setValue($command, new ArrayInput(['--force' => true], $command->getDefinition()));

    $publish = new ReflectionMethod(VendorPublishCommand::class, 'publishFile');
    $publish->setAccessible(true);
    $publish->invoke($command, $source_from, $target_to);

    expect(file_exists($target_to))->toBeTrue()
        ->and((string) file_get_contents($target_to))->not->toBe("<?php return ['from' => 1];");

    @unlink($source_from);
    @unlink($found_in_module);
    @unlink($target_to);
    @rmdir($source_dir);
    @rmdir($config_dir);
    @rmdir($root);
});

it('covers publishFile migration branch replacing destination with module migration path', function (): void {
    $files = new Filesystem();
    $command = new VendorPublishCommand($files);

    $migrations_subpath = config('modules.paths.generator.migration.path');
    $root = sys_get_temp_dir() . '/vendor_publish_migration_' . uniqid('', false);
    $migration_dir = $root . DIRECTORY_SEPARATOR . $migrations_subpath;
    mkdir($migration_dir, 0755, true);

    $source_from = $migration_dir . DIRECTORY_SEPARATOR . '2024_01_01_000000_create_publish_cov_table.php';
    $module_target = $migration_dir . DIRECTORY_SEPARATOR . '2023_01_01_000000_create_publish_cov_table.php';
    $dummy_to = $root . DIRECTORY_SEPARATOR . '2025_01_01_000000_create_publish_cov_table.php';
    file_put_contents($source_from, '<?php return "new";');
    file_put_contents($module_target, '<?php return "old";');

    $prop = new ReflectionProperty(VendorPublishCommand::class, 'modules');
    $prop->setAccessible(true);
    $prop->setValue($command, [
        'PublishCov' => [
            'path' => $root,
            'migrations' => [$module_target],
            'config' => [],
        ],
    ]);

    $components = new class
    {
        public function task(string $message): void {}

        public function twoColumnDetail(string $left, string $right): void {}
    };
    $components_prop = new ReflectionProperty(Illuminate\Console\Command::class, 'components');
    $components_prop->setAccessible(true);
    $components_prop->setValue($command, $components);

    $input_prop = new ReflectionProperty(Illuminate\Console\Command::class, 'input');
    $input_prop->setAccessible(true);
    $input_prop->setValue($command, new ArrayInput(['--force' => true], $command->getDefinition()));

    $publish = new ReflectionMethod(VendorPublishCommand::class, 'publishFile');
    $publish->setAccessible(true);
    $publish->invoke($command, $source_from, $dummy_to);

    expect((string) file_get_contents($module_target))->toContain('new');

    @unlink($source_from);
    @unlink($module_target);
    @rmdir($migration_dir);
    @rmdir($root);
});

it('returns false from module migration and config lookup when no module file matches', function (): void {
    $files = new Filesystem();
    $command = new VendorPublishCommand($files);

    $prop = new ReflectionProperty(VendorPublishCommand::class, 'modules');
    $prop->setAccessible(true);
    $prop->setValue($command, [
        'EmptyMod' => [
            'path' => sys_get_temp_dir(),
            'migrations' => [],
            'config' => [],
        ],
    ]);

    $migration_exists = new ReflectionMethod(VendorPublishCommand::class, 'moduleMigrationExists');
    $migration_exists->setAccessible(true);
    $config_exists = new ReflectionMethod(VendorPublishCommand::class, 'moduleConfigExists');
    $config_exists->setAccessible(true);

    expect($migration_exists->invoke($command, '2024_01_01_000000_no_match.php'))->toBeFalse()
        ->and($config_exists->invoke($command, 'missing-config.php'))->toBeFalse();
});
