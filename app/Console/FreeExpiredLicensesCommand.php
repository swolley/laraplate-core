<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class FreeExpiredLicensesCommand extends Command
{
    protected $signature = 'auth:free-expired-licenses';

    protected $description = 'Free expired licenses. <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        $this->info('Freeing expired licenses...');
        user_class();
        $this->output->error('User class is not Modules\Core\Models\User');

        return BaseCommand::SUCCESS;
    }
}
