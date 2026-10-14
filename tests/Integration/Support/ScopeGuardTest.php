<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Support\ScopeGuard;
use Modules\Core\Tests\Stubs\ImpureScopeFilterStubModel;
use Modules\Core\Tests\Stubs\ScopeFilterStubModel;

it('reads the declared filter scopes of a model and of the traits it uses', function (): void {
    $declarations = ScopeGuard::declarations(ScopeFilterStubModel::class);

    expect(array_keys($declarations))->toEqualCanonicalizing([
        'named', 'legacyNamed', 'priced', 'releasedBefore', 'flagged', 'eitherName', 'namedAnyOf',
    ])
        ->and($declarations['priced']['parameters'])->toBe([
            ['name' => 'min', 'type' => 'int', 'nullable' => false, 'optional' => false, 'default' => null],
            ['name' => 'max', 'type' => 'float', 'nullable' => true, 'optional' => true, 'default' => null],
        ])
        ->and($declarations['releasedBefore']['parameters'][0]['type'])->toBe('date')
        ->and($declarations['flagged']['parameters'][0])->toMatchArray(['optional' => true, 'default' => true])
        ->and($declarations['namedAnyOf']['unsupported'])->toBeString()
        ->and($declarations['priced']['unsupported'])->toBeNull();
});

it('declares a trait scope for every model using the trait', function (): void {
    expect(ScopeGuard::declarations(ImpureScopeFilterStubModel::class))->toHaveKey('named');
});

it('reports the client flag of each declaration', function (): void {
    $declarations = ScopeGuard::declarations(ScopeFilterStubModel::class);

    expect($declarations['named']['client'])->toBeTrue()
        ->and($declarations['releasedBefore']['client'])->toBeTrue()
        ->and($declarations['priced']['client'])->toBeFalse()
        ->and($declarations['legacyNamed']['client'])->toBeFalse();
});

it('refuses a scope that is not declared as a filter', function (string $scope): void {
    expect(fn (): array => ScopeGuard::assertFilterable(new ScopeFilterStubModel(), new ScopeFilter($scope)))
        ->toThrow(InvalidArgumentException::class, sprintf('"%s"', $scope));
})->with([
    'local scope without the attribute' => 'undeclared',
    'attribute on a method that is not a scope' => 'notAScope',
    'unknown name' => 'truncate',
    'declared name in another case' => 'PRICED',
]);

it('accepts an ACL-only scope in an ACL and refuses it in a client request', function (): void {
    $model = new ScopeFilterStubModel();

    expect(ScopeGuard::assertFilterable($model, new ScopeFilter('priced', [1])))->toBe([1])
        ->and(fn (): array => ScopeGuard::assertFilterable($model, new ScopeFilter('priced', [1]), client: true))
        ->toThrow(InvalidArgumentException::class, 'only in ACLs')
        ->and(ScopeGuard::assertFilterable($model, new ScopeFilter('named', ['a']), client: true))->toBe(['a']);
});

it('coerces the parameters to the declared types', function (string $scope, array $parameters, array $expected): void {
    expect(ScopeGuard::assertFilterable(new ScopeFilterStubModel(), new ScopeFilter($scope, $parameters)))->toBe($expected);
})->with([
    'int' => ['priced', [5], [5]],
    'int and float from strings' => ['priced', ['5', '9.5'], [5, 9.5]],
    'float from an int' => ['priced', [5, 10], [5, 10.0]],
    'null for a nullable parameter' => ['priced', [5, null], [5, null]],
    'optional left out' => ['flagged', [], []],
    'bool from a string' => ['flagged', ['false'], [false]],
    'bool' => ['flagged', [true], [true]],
    'string' => ['named', ['a'], ['a']],
    'legacy scope' => ['legacyNamed', ['a'], ['a']],
]);

it('turns an ISO 8601 string or a date into a CarbonInterface for a date parameter', function (mixed $value, string $format, string $expected): void {
    $parameters = ScopeGuard::assertFilterable(new ScopeFilterStubModel(), new ScopeFilter('releasedBefore', [$value]));

    expect($parameters)->toHaveCount(1)
        ->and($parameters[0])->toBeInstanceOf(CarbonInterface::class)
        ->and($parameters[0]->format($format))->toBe($expected);
})->with([
    'date only, in the app timezone' => ['2026-10-01', 'Y-m-d H:i:s', '2026-10-01 00:00:00'],
    'date and time with offset' => ['2026-10-01T10:30:00+02:00', 'c', '2026-10-01T10:30:00+02:00'],
    'UTC designator' => ['2026-10-01T10:30:00Z', 'c', '2026-10-01T10:30:00+00:00'],
    'resolved placeholder' => [Carbon::parse('2026-10-01 08:00:00'), 'Y-m-d H:i:s', '2026-10-01 08:00:00'],
]);

it('refuses parameters that do not fit the declaration', function (string $scope, array $parameters, string $reason): void {
    expect(fn (): array => ScopeGuard::assertFilterable(new ScopeFilterStubModel(), new ScopeFilter($scope, $parameters)))
        ->toThrow(InvalidArgumentException::class, $reason);
})->with([
    'missing required' => ['priced', [], 'needs at least 1'],
    'extra' => ['priced', [1, 2, 3], 'takes at most 2'],
    'string for an int' => ['priced', ['abc'], 'must be an int'],
    'float for an int' => ['priced', [1.5], 'must be an int'],
    'bool for an int' => ['priced', [true], 'must be an int'],
    'null for a required parameter' => ['priced', [null], 'must not be null'],
    'array' => ['named', [['a']], 'arrays and objects'],
    'object' => ['named', [new stdClass()], 'arrays and objects'],
    'int for a string' => ['named', [5], 'must be a string'],
    'not a boolean' => ['flagged', ['maybe'], 'must be a bool'],
    'date that is not ISO 8601' => ['releasedBefore', ['next monday'], 'ISO 8601'],
    'impossible date' => ['releasedBefore', ['2026-02-31'], 'ISO 8601'],
    'object for a date' => ['releasedBefore', [new stdClass()], 'arrays and objects'],
    'unsupported declaration' => ['namedAnyOf', ['a'], 'cannot be used as a filter'],
]);

it('never puts a parameter value in the error message', function (): void {
    try {
        ScopeGuard::assertFilterable(new ScopeFilterStubModel(), new ScopeFilter('priced', ['secret-value-42']));
        $this->fail('The parameter should have been refused.');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->not->toContain('secret-value-42')
            ->and($exception->getMessage())->toContain('priced')
            ->and($exception->getMessage())->toContain(ScopeFilterStubModel::class);
    }
});

it('applies a scope inside its own nested where in an AND group', function (): void {
    $query = ScopeFilterStubModel::query()->where('id', 1);

    ScopeGuard::apply($query, new ScopeFilter('eitherName', ['a', 'b']));

    expect($query->toSql())->toBe(
        'select * from "scope_filter_stub" where "id" = ? and ("scope_filter_stub"."name" = ? or "scope_filter_stub"."name" = ?)',
    )
        ->and($query->getBindings())->toBe([1, 'a', 'b']);
});

it('keeps the conditions of a scope inside its group in an OR group', function (): void {
    $query = ScopeFilterStubModel::query()->where('is_flagged', true)->where(function (Builder $group): void {
        $group->where('id', 1);
        ScopeGuard::apply($group, new ScopeFilter('priced', [1, 10]), 'or');
    });

    expect($query->toSql())->toBe(
        'select * from "scope_filter_stub" where "is_flagged" = ? and ("id" = ? or ("scope_filter_stub"."price" >= ? and "scope_filter_stub"."price" <= ?))',
    )
        ->and($query->getBindings())->toBe([true, 1, 1, 10.0]);
});

it('runs an optional parameter with its declared default and a date parameter as a date', function (): void {
    $flagged = ScopeFilterStubModel::query();
    ScopeGuard::apply($flagged, new ScopeFilter('flagged'));

    $released = ScopeFilterStubModel::query();
    ScopeGuard::apply($released, new ScopeFilter('releasedBefore', ['2026-10-01T00:00:00Z']));

    expect($flagged->getBindings())->toBe([true])
        ->and($released->getBindings()[0])->toBeInstanceOf(CarbonInterface::class);
});

it('narrows the rows of a real query', function (): void {
    Schema::create('scope_filter_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->decimal('price')->default(0);
        $table->boolean('is_flagged')->default(false);
        $table->timestamp('released_at')->nullable();
        $table->timestamps();
    });

    ScopeFilterStubModel::query()->insert([
        ['name' => 'cheap', 'price' => 1, 'is_flagged' => true],
        ['name' => 'middle', 'price' => 5, 'is_flagged' => false],
        ['name' => 'dear', 'price' => 50, 'is_flagged' => true],
    ]);

    $query = ScopeFilterStubModel::query()->where('is_flagged', true);
    ScopeGuard::apply($query, new ScopeFilter('eitherName', ['middle', 'dear']));

    expect($query->pluck('name')->all())->toBe(['dear']);
});

it('refuses a declared scope that does more than add where conditions', function (string $scope): void {
    $query = ImpureScopeFilterStubModel::query();

    expect(fn () => ScopeGuard::apply($query, new ScopeFilter($scope)))
        ->toThrow(InvalidArgumentException::class, sprintf('"%s"', $scope))
        ->and($query->getQuery()->wheres)->toBe([])
        ->and($query->toSql())->toBe('select * from "scope_filter_stub"');
})->with([
    'join' => 'joined',
    'order' => 'ordered',
    'selected columns' => 'selected',
    'group and having' => 'grouped',
    'limit and offset' => 'limited',
    'union' => 'unioned',
    'eager load' => 'eagerLoading',
    'conditions on another builder' => 'detached',
]);

it('refuses a client request for an ACL-only scope and an unknown boolean when applying', function (): void {
    expect(fn () => ScopeGuard::apply(ScopeFilterStubModel::query(), new ScopeFilter('priced', [1]), client: true))
        ->toThrow(InvalidArgumentException::class, 'only in ACLs')
        ->and(fn () => ScopeGuard::apply(ScopeFilterStubModel::query(), new ScopeFilter('priced', [1]), 'xor'))
        ->toThrow(InvalidArgumentException::class, 'and" or "or');
});

it('refuses a node whose parameters are not a list', function (): void {
    expect(fn (): ScopeFilter => new ScopeFilter('priced', ['min' => 1]))
        ->toThrow(InvalidArgumentException::class, 'list');
});

it('serializes the node with its exact keys', function (): void {
    expect((new ScopeFilter('priced', [1, 2.5]))->toArray())->toBe(['scope' => 'priced', 'parameters' => [1, 2.5]])
        ->and((new ScopeFilter('valid'))->toArray())->toBe(['scope' => 'valid', 'parameters' => []]);
});
