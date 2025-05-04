<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as BaseCommand;

class FreeAllLicensesCommand extends Command
{
    protected $signature = 'auth:free-all-licenses';

    protected $description = 'Free all the assigned licenses. <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $user_class = user_class();
            $user_class::whereNotNull('license_id')->update(['license_id' => null]);
            $this->output->success('All licenses have been freed');
            return BaseCommand::SUCCESS;
        } catch (\Throwable $ex) {
            $message = $ex->getMessage();
            $this->output->error($message);
            Log::error($message);

            return BaseCommand::FAILURE;
        }
    }
}
