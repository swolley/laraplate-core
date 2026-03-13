<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\FreeAllLicensesCommand;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(LaravelTestCase::class, RefreshDatabase::class);

it('frees all licenses and returns success', function (): void {
    /** @var class-string<User> $userClass */
    $userClass = user_class();

    $license = Modules\Core\Models\License::factory()->create();
    $user = $userClass::factory()->create(['license_id' => $license->id]);

    $command = app(FreeAllLicensesCommand::class);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
    $exitCode = $command->handle();

    expect($exitCode)->toBe(BaseCommand::SUCCESS);

    $user->refresh();
    expect($user->license_id)->toBeNull();
});

it('returns failure and logs error when an exception is thrown', function (): void {
    config(['auth.providers.users.model' => 'App\\Models\\NonExistentUserClass']);

    Illuminate\Support\Facades\Log::shouldReceive('error')->once();

    $command = app(FreeAllLicensesCommand::class);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
    $exit_code = $command->handle();

    expect($exit_code)->toBe(BaseCommand::FAILURE);
});
