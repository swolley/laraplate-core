<?php

declare(strict_types=1);

use Modules\Core\Console\TranslationMakeCommand;
use Modules\Core\Models\User;

it('resolves translation stub paths and builds model and migration stubs', function (): void {
    $command = app(TranslationMakeCommand::class);
    $command->setLaravel(app());

    expect($command->getName())->toBe('make:translation');

    $core_resource = new ReflectionMethod(TranslationMakeCommand::class, 'coreResourcePath');
    $core_resource->setAccessible(true);
    expect(is_file($core_resource->invoke($command, 'stubs/translation.stub')))->toBeTrue()
        ->and(is_file($core_resource->invoke($command, 'stubs/translation_migration.stub')))->toBeTrue();

    $resolve_output_paths = new ReflectionMethod(TranslationMakeCommand::class, 'resolveOutputPaths');
    $resolve_output_paths->setAccessible(true);
    $module_paths = $resolve_output_paths->invoke($command, User::class);
    expect($module_paths)->toHaveKeys(['new_class_path', 'new_migration_path']);

    $app_paths = $resolve_output_paths->invoke($command, 'App\\Models\\Post');
    expect($app_paths['new_class_path'])->toContain('app/Models/Translations')
        ->and($app_paths['new_migration_path'])->toContain('database/migrations');

    $format_casts = new ReflectionMethod(TranslationMakeCommand::class, 'formatCastsForStub');
    $format_casts->setAccessible(true);
    $casts = $format_casts->invoke($command, ['title' => 'string', 'is_active' => 'boolean']);
    expect($casts)->toBe([
        'title' => "'title' => 'string'",
        'is_active' => "'is_active' => 'boolean'",
    ]);

    $format_hidden = new ReflectionMethod(TranslationMakeCommand::class, 'formatHiddenForStub');
    $format_hidden->setAccessible(true);
    $hidden = $format_hidden->invoke($command, ['secret']);
    expect($hidden)->toBe(["'secret'"]);

    $build_list_block = new ReflectionMethod(TranslationMakeCommand::class, 'buildListBlock');
    $build_list_block->setAccessible(true);
    expect($build_list_block->invoke($command, []))->toBe('')
        ->and($build_list_block->invoke($command, ["'title'", "'body'"]))->toBe("'title',\n        'body',");

    $map_cast = new ReflectionMethod(TranslationMakeCommand::class, 'mapCastToMigrationType');
    $map_cast->setAccessible(true);
    expect($map_cast->invoke($command, 'array'))->toBe('json')
        ->and($map_cast->invoke($command, 'datetime'))->toBe('datetime')
        ->and($map_cast->invoke($command, 'unknown'))->toBe('string');

    $build_model_stub = new ReflectionMethod(TranslationMakeCommand::class, 'buildTranslationModelStub');
    $build_model_stub->setAccessible(true);
    $model_stub = $build_model_stub->invoke(
        $command,
        User::class,
        'User',
        'Modules\\Core\\Models\\Translations\\UserTranslation',
        ["'title'"],
        ['title' => "'title' => 'string'"],
        ["'password'"],
        'user',
        'user_id',
    );
    expect($model_stub)->toContain('class UserTranslation')
        ->and($model_stub)->toContain("'title'")
        ->and($model_stub)->toContain("'password'");

    $build_migration_stub = new ReflectionMethod(TranslationMakeCommand::class, 'buildTranslationMigrationStub');
    $build_migration_stub->setAccessible(true);
    $migration_stub = $build_migration_stub->invoke(
        $command,
        'UserTranslation',
        ['title', 'meta', 'published_at'],
        ['title' => 'string', 'meta' => 'array', 'published_at' => 'datetime'],
        'user_id',
        'users',
    );
    expect($migration_stub)->toContain("\$table->string('title')")
        ->and($migration_stub)->toContain("\$table->json('meta')")
        ->and($migration_stub)->toContain("\$table->datetime('published_at')");
});

it('resolves core resource path via package fallback when stub is not under module_path', function (): void {
    $command = app(TranslationMakeCommand::class);
    $command->setLaravel(app());

    $core_resource = new ReflectionMethod(TranslationMakeCommand::class, 'coreResourcePath');
    $core_resource->setAccessible(true);
    $resolved = $core_resource->invoke($command, 'stubs/__nonexistent_stub_for_fallback_probe__.stub');

    expect(is_file($resolved))->toBeFalse()
        ->and($resolved)->toContain('stubs')
        ->and($resolved)->toContain('__nonexistent_stub_for_fallback_probe__');
});

it('maps all cast types to migration column helpers', function (): void {
    $command = app(TranslationMakeCommand::class);
    $command->setLaravel(app());
    $map_cast = new ReflectionMethod(TranslationMakeCommand::class, 'mapCastToMigrationType');
    $map_cast->setAccessible(true);

    expect($map_cast->invoke($command, 'int'))->toBe('integer')
        ->and($map_cast->invoke($command, 'float'))->toBe('float')
        ->and($map_cast->invoke($command, 'boolean'))->toBe('boolean')
        ->and($map_cast->invoke($command, 'date'))->toBe('date')
        ->and($map_cast->invoke($command, 'object'))->toBe('json');
});
