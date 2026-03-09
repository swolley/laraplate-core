<?php

declare(strict_types=1);

use Modules\Core\Rules\QueryColumn;

it('passes for string value', function (): void {
    $rule = new QueryColumn;
    $fail_called = false;
    $rule->validate('column', 'users.id', function (string $message) use (&$fail_called): void {
        $fail_called = true;
    });

    expect($fail_called)->toBeFalse();
});

it('passes for array with name and type', function (): void {
    $rule = new QueryColumn;
    $fail_called = false;
    $rule->validate('column', ['name' => 'email', 'type' => 'string'], function (string $message) use (&$fail_called): void {
        $fail_called = true;
    });

    expect($fail_called)->toBeFalse();
});

it('fails when value is not string nor array', function (): void {
    $rule = new QueryColumn;
    $message = null;
    $rule->validate('column', 123, function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe("column doesn't have a correct format");
});

it('fails when array misses name key', function (): void {
    $rule = new QueryColumn;
    $message = null;
    $rule->validate('column', ['type' => 'string'], function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe("column doesn't have a correct format");
});

it('fails when array misses type key', function (): void {
    $rule = new QueryColumn;
    $message = null;
    $rule->validate('column', ['name' => 'email'], function (string $m) use (&$message): void {
        $message = $m;
    });

    expect($message)->toBe("column doesn't have a correct format");
});
