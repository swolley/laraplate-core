<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Cms\Models\Category;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;
use Modules\Core\Services\DynamicContentsService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    DynamicContentsService::reset();
});

/**
 * @return class-string
 */
function dynamic_contents_invoke_get_module_model_class(string $local_class, string $target_class): string
{
    $ref = new ReflectionClass(DynamicContentsService::class);
    $method = $ref->getMethod('getModuleModelClass');

    return $method->invoke(null, $local_class, $target_class);
}

function dynamic_contents_module_root_prefix(string $class): string
{
    $ref = new ReflectionClass(DynamicContentsService::class);
    $method = $ref->getMethod('moduleRootNamespacePrefix');

    return $method->invoke(null, $class);
}

it('resolves App root prefix from an App FQCN', function (): void {
    expect(dynamic_contents_module_root_prefix(User::class))->toBe('App\\');
});

it('resolves Modules root prefix from a module FQCN', function (): void {
    expect(dynamic_contents_module_root_prefix(Preset::class))->toBe('Modules\\Core\\');
});

it('throws when FQCN has no supported module root', function (): void {
    dynamic_contents_module_root_prefix('Acme\\Models\\Foo');
})->throws(UnexpectedValueException::class);

it('maps core target model to the local module namespace for presets', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Category::class, Preset::class);

    expect($resolved)->toBe(Modules\Cms\Models\Preset::class);
});

it('maps core target pivot to the local module namespace for presettables', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Category::class, Presettable::class);

    expect($resolved)->toBe(Modules\Cms\Models\Pivot\Presettable::class);
});

it('returns the target class unchanged when local and target share the same module', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Presettable::class, Presettable::class);

    expect($resolved)->toBe(Presettable::class);
});

it('throws when the resolved class is not autoloadable', function (): void {
    dynamic_contents_invoke_get_module_model_class(User::class, Preset::class);
})->throws(UnexpectedValueException::class, 'Target class not found');
