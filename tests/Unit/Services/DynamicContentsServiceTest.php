<?php

declare(strict_types=1);

use App\Models\User;
use Modules\CMS\Models\Category;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;
use Modules\Core\Services\DynamicContentsService;


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

it('maps App target model to app namespace', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(User::class, User::class);

    expect($resolved)->toBe(User::class);
});

it('maps module target model to the local module namespace', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Preset::class, Preset::class);

    expect($resolved)->toBe(Preset::class);
});

it('throws when target namespace cannot be mapped', function (): void {
    dynamic_contents_invoke_get_module_model_class(User::class, 'Acme\\Models\\Foo');
})->throws(UnexpectedValueException::class);

it('maps core target model to the local module namespace for presets', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Category::class, Preset::class);

    expect($resolved)->toBe(Modules\CMS\Models\Preset::class);
});

it('maps core target pivot to the local module namespace for presettables', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Category::class, Presettable::class);

    expect($resolved)->toBe(Modules\CMS\Models\Pivot\Presettable::class);
});

it('returns the target class unchanged when local and target share the same module', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Presettable::class, Presettable::class);

    expect($resolved)->toBe(Presettable::class);
});

it('throws when the resolved class is not autoloadable', function (): void {
    dynamic_contents_invoke_get_module_model_class(User::class, Preset::class);
})->throws(UnexpectedValueException::class, 'Target class not found');

it('uses distinct memo cache keys for different module Presettable classes', function (): void {
    $ref = new ReflectionClass(DynamicContentsService::class);
    $method = $ref->getMethod('presettableMemoKey');
    $method->setAccessible(true);

    $cms_key = $method->invoke(null, Modules\CMS\Models\Pivot\Presettable::class);
    $erp_key = $method->invoke(null, Modules\ERP\Models\Pivot\Presettable::class);

    expect($cms_key)->not->toBe($erp_key)
        ->and($cms_key)->toStartWith('core.dynamic_contents.presettables:')
        ->and($erp_key)->toStartWith('core.dynamic_contents.presettables:');
});
