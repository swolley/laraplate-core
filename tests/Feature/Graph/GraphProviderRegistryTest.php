<?php

declare(strict_types=1);

use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Tests\Stubs\Graphs\RegistryTestProvider;

it('resolves entity providers before module providers', function (): void {
    $registry = new GraphProviderRegistry();

    $registry->register(new RegistryTestProvider('module'), 'cms');
    $registry->register(new RegistryTestProvider('entity'), 'cms', 'contents');

    expect($registry->providerFor('cms', 'contents')?->defaultRelations('cms', 'contents'))->toBe(['entity']);
    expect($registry->providerFor('cms', 'tags')?->defaultRelations('cms', 'tags'))->toBe(['module']);
    expect($registry->providerFor('erp', 'customers'))->toBeNull();
});

it('binds the registry contract in the container', function (): void {
    expect(app(GraphProviderRegistryInterface::class))->toBeInstanceOf(GraphProviderRegistry::class);
});
