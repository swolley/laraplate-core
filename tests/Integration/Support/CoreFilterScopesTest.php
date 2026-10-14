<?php

declare(strict_types=1);

use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Models\Field;
use Modules\Core\Models\License;
use Modules\Core\Models\Taxonomy;
use Modules\Core\Models\User;
use Modules\Core\Support\ScopeGuard;
use Modules\Core\Tests\Stubs\ActivationStubModel;
use Modules\Core\Tests\Stubs\ValidityStubModel;

it('declares the validity scopes with the client flag of the spec', function (): void {
    $declarations = ScopeGuard::declarations(ValidityStubModel::class);

    expect(array_keys($declarations))->toEqualCanonicalizing([
        'valid', 'published', 'draft', 'scheduled', 'expired', 'validAt', 'expiredAt', 'expiring',
    ]);

    foreach ($declarations as $declaration) {
        expect($declaration['client'])->toBeTrue()
            ->and($declaration['unsupported'])->toBeNull();
    }
});

it('leaves the validity scopes that order or duplicate another undeclared', function (): void {
    expect(ScopeGuard::declarations(ValidityStubModel::class))->not->toHaveKeys(['validityOrdered', 'withValidityFilter']);
});

it('declares the activation scopes as ACL-only', function (): void {
    $declarations = ScopeGuard::declarations(ActivationStubModel::class);

    expect(array_keys($declarations))->toEqualCanonicalizing(['active', 'inactive'])
        ->and($declarations['active']['client'])->toBeFalse()
        ->and($declarations['inactive']['client'])->toBeFalse();
});

it('declares the trait scopes on the real models using the traits', function (): void {
    $validity = ['valid', 'published', 'draft', 'scheduled', 'expired', 'validAt', 'expiredAt', 'expiring'];

    expect(array_keys(ScopeGuard::declarations(User::class)))->toEqualCanonicalizing($validity)
        ->and(array_keys(ScopeGuard::declarations(Field::class)))->toEqualCanonicalizing(['active', 'inactive'])
        ->and(array_keys(ScopeGuard::declarations(Taxonomy::class)))->toEqualCanonicalizing([...$validity, 'active', 'inactive']);
});

it('does not declare a model scope that replaces a trait scope without the attribute', function (): void {
    expect(ScopeGuard::declarations(License::class))->not->toHaveKey('expired')
        ->and(ScopeGuard::declarations(License::class))->toHaveKeys(['valid', 'expiredAt']);
});

it('reads the declared parameters of the parameterised validity scopes', function (): void {
    $declarations = ScopeGuard::declarations(ValidityStubModel::class);

    expect($declarations['validAt']['parameters'][0]['type'])->toBe('date')
        ->and($declarations['expiredAt']['parameters'][0]['type'])->toBe('date')
        ->and($declarations['expiring']['parameters'][0])->toMatchArray(['type' => 'int', 'nullable' => true, 'optional' => true]);
});

it('keeps every declared scope pure on a real query', function (string $model_class, string $scope, array $parameters, bool $client): void {
    $query = (new $model_class())->newQuery();
    $before = $query->toRawSql();

    ScopeGuard::apply($query, new ScopeFilter($scope, $parameters), client: $client);

    expect($query->toRawSql())->not->toBe($before);
})->with(function (): Generator {
    foreach (['valid', 'published', 'draft', 'scheduled', 'expired'] as $scope) {
        yield 'validity ' . $scope => [User::class, $scope, [], true];
    }

    yield 'validity validAt' => [User::class, 'validAt', ['2026-10-14'], true];

    yield 'validity expiredAt' => [User::class, 'expiredAt', ['2026-10-14T10:00:00Z'], true];

    yield 'validity expiring' => [User::class, 'expiring', [24], true];

    yield 'validity expiring default window' => [User::class, 'expiring', [], true];

    yield 'activation active' => [Field::class, 'active', [], false];

    yield 'activation inactive' => [Field::class, 'inactive', [], false];
});

it('refuses the activation scopes in a client request', function (string $scope): void {
    expect(fn () => ScopeGuard::apply((new Field())->newQuery(), new ScopeFilter($scope), client: true))
        ->toThrow(InvalidArgumentException::class, 'only in ACLs');
})->with(['active', 'inactive']);

it('refuses the scopes that are not declared', function (string $scope, array $parameters): void {
    expect(fn () => ScopeGuard::apply((new User())->newQuery(), new ScopeFilter($scope, $parameters)))
        ->toThrow(InvalidArgumentException::class, 'is not a filter scope');
})->with([
    'validityOrdered' => ['validityOrdered', []],
    'withValidityFilter' => ['withValidityFilter', ['2026-10-14']],
]);
