<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Modules\Core\Graph\Contracts\GraphProviderRulesInterface;
use Modules\Core\Graph\GraphEntityResolver;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Graph\GraphProviderRuleEnforcer;

final class GraphProviderRulesModel extends Model
{
    protected $table = 'rules_entities';
}

final class GraphProviderRulesTestProvider implements GraphProviderInterface, GraphProviderRulesInterface
{
    public function defaultRelations(string $module, string $entity): array
    {
        return [];
    }

    public function summaryFields(string $module, string $entity): array
    {
        return [];
    }

    public function edgeType(string $module, string $entity, string $relation): ?string
    {
        return null;
    }

    public function excludedRelations(string $module, string $entity): array
    {
        return [];
    }

    public function allowedRelationPaths(string $module, string $entity): array
    {
        return ['allowed', 'allowed.children'];
    }

    public function maxDepth(string $module, string $entity): ?int
    {
        return 2;
    }

    public function maxRelationLimit(string $module, string $entity, string $relation): ?int
    {
        return $relation === 'allowed' ? 3 : null;
    }
}

it('rejects requested relation paths outside provider allow lists', function (): void {
    $registry = new GraphProviderRegistry();
    $registry->register(new GraphProviderRulesTestProvider(), 'app', 'rules_entities');

    $enforcer = new GraphProviderRuleEnforcer(new GraphEntityResolver(), $registry);
    $model = new GraphProviderRulesModel();

    expect(fn () => $enforcer->assertRequestAllowed($model, ['denied'], 1, 1))
        ->toThrow(ValidationException::class, "Relation path 'denied' is not allowed by provider.");
});

it('rejects depth greater than the provider maximum', function (): void {
    $registry = new GraphProviderRegistry();
    $registry->register(new GraphProviderRulesTestProvider(), 'app', 'rules_entities');

    $enforcer = new GraphProviderRuleEnforcer(new GraphEntityResolver(), $registry);
    $model = new GraphProviderRulesModel();

    expect(fn () => $enforcer->assertRequestAllowed($model, ['allowed.children'], 3, 1))
        ->toThrow(ValidationException::class, 'Graph depth exceeds provider maximum.');
});

it('rejects relation limits greater than the provider maximum for a relation', function (): void {
    $registry = new GraphProviderRegistry();
    $registry->register(new GraphProviderRulesTestProvider(), 'app', 'rules_entities');

    $enforcer = new GraphProviderRuleEnforcer(new GraphEntityResolver(), $registry);
    $model = new GraphProviderRulesModel();

    expect(fn () => $enforcer->assertRelationAllowed($model, 'allowed', 4))
        ->toThrow(ValidationException::class, "Relation 'allowed' relation_limit exceeds provider maximum.");
});
