<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Pivot\Presettable as AppPresettable;
use App\Models\Preset as AppPreset;
use App\Models\User;
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
    $method->setAccessible(true);

    return $method->invoke(DynamicContentsService::getInstance(), $local_class, $target_class);
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

it('maps core target model to the app namespace for app presets', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Page::class, Preset::class);

    expect($resolved)->toBe(AppPreset::class);
});

it('maps core target pivot to the app namespace for app presettables', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Page::class, Presettable::class);

    expect($resolved)->toBe(AppPresettable::class);
});

it('returns the target class unchanged when local and target share the same module', function (): void {
    $resolved = dynamic_contents_invoke_get_module_model_class(Presettable::class, Presettable::class);

    expect($resolved)->toBe(Presettable::class);
});

it('throws when the resolved class is not autoloadable', function (): void {
    dynamic_contents_invoke_get_module_model_class(User::class, 'Modules\Core\Models\NonExistentPresetStub');
})->throws(UnexpectedValueException::class, 'Target class not found');

it('uses distinct memo cache keys for different Presettable classes', function (): void {
    $ref = new ReflectionClass(DynamicContentsService::class);
    $method = $ref->getMethod('presettableMemoKey');
    $method->setAccessible(true);

    $service = DynamicContentsService::getInstance();
    $core_key = $method->invoke($service, Presettable::class);
    $app_key = $method->invoke($service, AppPresettable::class);

    expect($core_key)->not->toBe($app_key)
        ->and($core_key)->toStartWith('core.dynamic_contents.presettables:')
        ->and($app_key)->toStartWith('core.dynamic_contents.presettables:');
});
