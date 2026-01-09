<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Models\User;
use Modules\Core\Overrides\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class FreeExpiredLicensesCommand extends Command
{
    protected $signature = 'auth:free-expired-licenses';

    protected $description = 'Free expired licenses. <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        $this->info('Freeing expired licenses...');
        $user_class = user_class();

        if (! is_subclass_of($user_class, User::class)) {
            $this->output->error('User class is not ' . User::class);

            return BaseCommand::FAILURE;
        }

        $user_class::query()->join('licenses', 'users.license_id', '=', 'licenses.id')
            ->whereNotNull('licenses.valid_to')
            ->where('licenses.valid_to', '<', now())
            ->update(['license_id' => null]);

        $this->output->success('Expired licenses have been freed');

        return BaseCommand::SUCCESS;
    }
}
