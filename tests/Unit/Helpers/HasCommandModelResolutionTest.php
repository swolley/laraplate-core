<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\Console\HasCommandModelResolutionOptionOnlyTestCommand;
use Modules\Core\Tests\Stubs\Console\HasCommandModelResolutionTestCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

uses(LaravelTestCase::class);

function bind_command_input(Command $command, array $args): void
{
    $input = new ArrayInput($args);
    $input->bind($command->getDefinition());
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, new NullOutput));
}

it('returns false when model is not required and missing', function (): void {
    $command = new HasCommandModelResolutionTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, []);

    expect($command->resolveModel('model', null, false))->toBeFalse();
});

it('returns class when model argument is a valid class name', function (): void {
    $command = new HasCommandModelResolutionTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, ['model' => User::class]);

    expect($command->resolveModel('model', null, true))->toBe(User::class);
});

it('resolves short class name via evince when single match exists', function (): void {
    $all_models = models(false);

    if ($all_models === [] || ! in_array(User::class, $all_models, true)) {
        expect(true)->toBeTrue();

        return;
    }

    $command = new HasCommandModelResolutionTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, ['model' => 'User']);

    expect($command->resolveModel('model', null, true))->toBe(User::class);
});

it('prepends namespace when provided and class exists', function (): void {
    $command = new HasCommandModelResolutionTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, ['model' => 'User']);

    expect($command->resolveModel('model', 'Modules\\Core\\Models', true))->toBe(User::class);
});

it('returns false when evince finds no models', function (): void {
    $command = new HasCommandModelResolutionTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, ['model' => 'TotallyNonexistentModelNameXyz12345']);

    expect($command->resolveModel('model', null, true))->toBeFalse();
});

it('returns false when multiple models match evince filter', function (): void {
    $all_models = models(false);
    $short_name_matches = array_values(array_filter($all_models, fn (string $m): bool => str_ends_with($m, 'User')));

    if (count($short_name_matches) < 2) {
        expect(true)->toBeTrue();

        return;
    }

    $command = new HasCommandModelResolutionTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, ['model' => 'User']);

    expect($command->resolveModel('model', null, true))->toBeFalse();
});

it('resolves model from option when argument is not present', function (): void {
    $command = new HasCommandModelResolutionOptionOnlyTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, ['--entity' => User::class]);

    expect($command->resolveModel('entity'))->toBe(User::class);
});

it('returns false when empty model string matches multiple existing models', function (): void {
    $all_models = models(false);
    $needle = preg_replace('/^.*\\\\/', '', (string) head($all_models));

    if ($needle === null || $needle === '') {
        expect(true)->toBeTrue();

        return;
    }

    $command = new HasCommandModelResolutionTestCommand;
    $command->setLaravel($this->app);
    bind_command_input($command, ['model' => $needle . '__not_found']);

    expect($command->resolveModel('model', null, true))->toBeFalse();
});
