<?php

declare(strict_types=1);

use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\Console\HasCommandUtilsTestCommand;

uses(LaravelTestCase::class);

it('returns null when attribute is not in validations', function (): void {
    $command = new HasCommandUtilsTestCommand;

    expect($command->testValidationCallback('email', 'a@b.com', ['name' => 'required']))->toBeNull();
});

it('returns null when validation passes', function (): void {
    $command = new HasCommandUtilsTestCommand;

    expect($command->testValidationCallback('email', 'ok@example.com', ['email' => 'email']))->toBeNull();
});

it('returns first message when validation fails', function (): void {
    $command = new HasCommandUtilsTestCommand;

    $message = $command->testValidationCallback('email', 'not-an-email', ['email' => 'email']);

    expect($message)->toBeString()->not->toBeEmpty();
});
