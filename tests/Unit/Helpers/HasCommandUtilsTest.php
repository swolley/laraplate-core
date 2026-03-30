<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Modules\Core\Helpers\HasCommandUtils;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

final class HasCommandUtilsTestCommand extends Command
{
    use HasCommandUtils;

    protected $signature = 'test:utils';

    protected $description = 'test';

    public function testValidationCallback(string $attribute, string $value, array $validations): ?string
    {
        $method = new ReflectionMethod($this, 'validationCallback');

        return $method->invoke($this, $attribute, $value, $validations);
    }
}

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
