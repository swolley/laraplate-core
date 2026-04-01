<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Modules\Core\Console\FreeExpiredLicensesCommand;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('frees licenses whose validity has expired', function (): void {
    /** @var class-string<User> $userClass */
    $userClass = user_class();

    $licenseActive = License::factory()->create();
    $licenseActive->valid_to = now()->addDay();
    $licenseActive->save();

    $licenseExpired = License::factory()->create();
    $licenseExpired->valid_to = today()->subDay();
    $licenseExpired->save();

    $activeUser = $userClass::factory()->create(['license_id' => $licenseActive->id]);
    $expiredUser = $userClass::factory()->create(['license_id' => $licenseExpired->id]);

    expect(License::query()->expired()->pluck('id')->toArray())->toContain($licenseExpired->id);

    $command = app(FreeExpiredLicensesCommand::class);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
    $exitCode = $command->handle();

    expect($exitCode)->toBe(BaseCommand::SUCCESS);

    $activeUser->refresh();
    $expiredUser->refresh();

    expect($activeUser->license_id)->toBe($licenseActive->id)
        ->and($expiredUser->license_id)->toBeNull();
});

it('returns failure when user class is not User or subclass', function (): void {
    config(['auth.providers.users.model' => Illuminate\Database\Eloquent\Model::class]);

    $command = app(FreeExpiredLicensesCommand::class);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
    $exit_code = $command->handle();

    expect($exit_code)->toBe(BaseCommand::FAILURE);
});
