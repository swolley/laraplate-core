<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Support\Facades\Log;
use Modules\Core\Overrides\Command;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class FreeAllLicensesCommand extends Command
{
    #[Override]
    protected $signature = 'auth:free-all-licenses';

    #[Override]
    protected $description = 'Free all the assigned licenses. <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $user_class = user_class();
            $user_class::query()->whereNotNull('license_id')->update(['license_id' => null]);
            $this->output->success('All licenses have been freed');

            return BaseCommand::SUCCESS;
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            $this->output->error($message);
            Log::error($message);

            return BaseCommand::FAILURE;
        }
    }
}
