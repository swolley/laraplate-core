<?php

declare(strict_types=1);

use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\GraphProviderRegistry;

final class RegistryTestProvider implements GraphProviderInterface
{
    public function __construct(private readonly string $name) {}

    public function defaultRelations(string $module, string $entity): array
    {
        return [$this->name];
    }

    public function summaryFields(string $module, string $entity): array
    {
        return ['name'];
    }

    public function edgeType(string $module, string $entity, string $relation): ?string
    {
        return $this->name . ':' . $relation;
    }

    public function excludedRelations(string $module, string $entity): array
    {
        return [];
    }
}

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
