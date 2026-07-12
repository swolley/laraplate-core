<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\Contracts\GraphProviderRulesInterface;

final class GraphProviderRuleEnforcer
{
    public function __construct(
        private readonly GraphEntityResolver $entities,
        private readonly GraphProviderRegistryInterface $providers,
    ) {}

    /**
     * @param  list<string>  $relationPaths
     */
    public function assertRequestAllowed(Model $model, array $relationPaths, int $depth, int $relationLimit): void
    {
        $module = $this->entities->moduleFor($model);
        $entity = $this->entities->entityFor($model);
        $provider = $this->rulesProviderFor($module, $entity);

        if (! $provider instanceof GraphProviderRulesInterface) {
            return;
        }

        $maxDepth = $provider->maxDepth($module, $entity);

        if ($maxDepth !== null && $depth > $maxDepth) {
            throw ValidationException::withMessages(['depth' => 'Graph depth exceeds provider maximum.']);
        }

        $allowedRelationPaths = $provider->allowedRelationPaths($module, $entity);

        if ($allowedRelationPaths !== []) {
            foreach ($relationPaths as $relationPath) {
                if (! in_array($relationPath, $allowedRelationPaths, true)) {
                    throw ValidationException::withMessages(['relations' => sprintf("Relation path '%s' is not allowed by provider.", $relationPath)]);
                }
            }
        }

        foreach ($relationPaths as $relationPath) {
            $relation = explode('.', $relationPath)[0] ?? $relationPath;
            $this->assertRelationLimit($provider, $module, $entity, $relation, $relationLimit);
        }
    }

    public function assertRelationAllowed(Model $source, string $relation, int $relationLimit): void
    {
        $module = $this->entities->moduleFor($source);
        $entity = $this->entities->entityFor($source);
        $provider = $this->rulesProviderFor($module, $entity);

        if (! $provider instanceof GraphProviderRulesInterface) {
            return;
        }

        $this->assertRelationLimit($provider, $module, $entity, $relation, $relationLimit);
    }

    private function rulesProviderFor(string $module, string $entity): ?GraphProviderRulesInterface
    {
        $provider = $this->providers->providerFor($module, $entity);

        return $provider instanceof GraphProviderRulesInterface ? $provider : null;
    }

    private function assertRelationLimit(GraphProviderRulesInterface $provider, string $module, string $entity, string $relation, int $relationLimit): void
    {
        $maxRelationLimit = $provider->maxRelationLimit($module, $entity, $relation);

        if ($maxRelationLimit !== null && $relationLimit > $maxRelationLimit) {
            throw ValidationException::withMessages(['relation_limit' => sprintf("Relation '%s' relation_limit exceeds provider maximum.", $relation)]);
        }
    }
}
