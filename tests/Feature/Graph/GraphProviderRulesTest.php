<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\GraphEntityResolver;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Graph\GraphProviderRuleEnforcer;
use Modules\Core\Tests\Stubs\Graphs\GraphProviderRulesModel;
use Modules\Core\Tests\Stubs\Graphs\GraphProviderRulesTestProvider;

it('rejects requested relation paths outside provider allow lists', function (): void {
    $registry = new GraphProviderRegistry();
    $registry->register(new GraphProviderRulesTestProvider(), 'core', 'rules_entities');

    $enforcer = new GraphProviderRuleEnforcer(new GraphEntityResolver(), $registry);
    $model = new GraphProviderRulesModel();

    expect(fn () => $enforcer->assertRequestAllowed($model, ['denied'], 1, 1))
        ->toThrow(ValidationException::class, "Relation path 'denied' is not allowed by provider.");
});

it('rejects depth greater than the provider maximum', function (): void {
    $registry = new GraphProviderRegistry();
    $registry->register(new GraphProviderRulesTestProvider(), 'core', 'rules_entities');

    $enforcer = new GraphProviderRuleEnforcer(new GraphEntityResolver(), $registry);
    $model = new GraphProviderRulesModel();

    expect(fn () => $enforcer->assertRequestAllowed($model, ['allowed.children'], 3, 1))
        ->toThrow(ValidationException::class, 'Graph depth exceeds provider maximum.');
});

it('rejects relation limits greater than the provider maximum for a relation', function (): void {
    $registry = new GraphProviderRegistry();
    $registry->register(new GraphProviderRulesTestProvider(), 'core', 'rules_entities');

    $enforcer = new GraphProviderRuleEnforcer(new GraphEntityResolver(), $registry);
    $model = new GraphProviderRulesModel();

    expect(fn () => $enforcer->assertRelationAllowed($model, 'allowed', 4))
        ->toThrow(ValidationException::class, "Relation 'allowed' relation_limit exceeds provider maximum.");
});
