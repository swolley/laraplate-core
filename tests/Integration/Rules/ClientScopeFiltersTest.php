<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Rules\ClientScopeFilters;
use Modules\Core\Tests\Stubs\FailingScopeFilterStubModel;
use Modules\Core\Tests\Stubs\ScopeFilterStubModel;

/**
 * @param  array<mixed>  $filters
 * @return list<string>
 */
function clientscope_errors(?Model $model, array $filters): array
{
    $validator = Validator::make(['filters' => $filters], ['filters' => [new ClientScopeFilters($model)]]);

    return $validator->errors()->get('filters');
}

it('accepts a client scope node at any depth', function (): void {
    expect(clientscope_errors(new ScopeFilterStubModel(), ['operator' => 'or', 'filters' => [['scope' => 'named', 'parameters' => ['a']]]]))->toBe([]);
});

it('refuses a client scope that throws an exception of its own without echoing its message or parameter', function (): void {
    $errors = clientscope_errors(new FailingScopeFilterStubModel(), [['scope' => 'failing', 'parameters' => ['secret-value-42']]]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('filters.0')
        ->and($errors[0])->toContain('"failing"')
        ->and($errors[0])->not->toContain('secret-value-42')
        ->and($errors[0])->not->toContain('internal detail')
        ->and($errors[0])->not->toContain(FailingScopeFilterStubModel::class);
});

it('refuses a scope node when the entity cannot be resolved', function (): void {
    expect(clientscope_errors(null, [['scope' => 'named', 'parameters' => ['a']]]))->toHaveCount(1);
});

it('leaves a malformed scope node to the shape rule', function (): void {
    expect(clientscope_errors(new ScopeFilterStubModel(), [['scope' => 'named', 'parameters' => 'a']]))->toBe([]);
});
