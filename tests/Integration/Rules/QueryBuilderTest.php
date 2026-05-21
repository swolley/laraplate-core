<?php

declare(strict_types=1);

use Modules\Core\Rules\QueryBuilder;

it('fails when value is not array', function (): void {
    $rule = new QueryBuilder;
    $message = null;
    $rule->validate('filters', 'invalid', function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe("filters doesn't have a correct format");
});

it('passes for empty list', function (): void {
    $rule = new QueryBuilder;
    $fail_called = false;
    $rule->validate('filters', [], function (string $m) use (&$fail_called): void {
        $fail_called = true;
    });

    expect($fail_called)->toBeFalse();
});

it('passes for associative array with property operator value', function (): void {
    $rule = new QueryBuilder;
    $fail_called = false;
    $rule->validate('filter', [
        'property' => 'name',
        'operator' => '=',
        'value' => 'test',
    ], function (string $m) use (&$fail_called): void {
        $fail_called = true;
    });

    expect($fail_called)->toBeFalse();
});

it('fails when associative array has neither property nor filters', function (): void {
    $rule = new QueryBuilder;
    $message = null;
    $rule->validate('filter', ['foo' => 'bar'], function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe("filter doesn't have a correct format");
});

it('fails when property is present but operator missing', function (): void {
    $rule = new QueryBuilder;
    $message = null;
    $rule->validate('filter', [
        'property' => 'name',
        'value' => 'test',
    ], function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe('filter "operator" is required');
});

it('fails when property is present but value missing', function (): void {
    $rule = new QueryBuilder;
    $message = null;
    $rule->validate('filter', [
        'property' => 'name',
        'operator' => '=',
    ], function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe('filter "value" is required');
});

it('passes for nested filters list', function (): void {
    $rule = new QueryBuilder;
    $fail_called = false;
    $rule->validate('filters', [
        'filters' => [
            ['property' => 'a', 'operator' => '=', 'value' => 1],
        ],
    ], function (string $m) use (&$fail_called): void {
        $fail_called = true;
    });

    expect($fail_called)->toBeFalse();
});

it('fails when filters key is not a list', function (): void {
    $rule = new QueryBuilder;
    $message = null;
    $rule->validate('filter', [
        'filters' => ['property' => 'a', 'operator' => '=', 'value' => 1],
    ], function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe('filter filters doesn\'t have a correct format');
});
