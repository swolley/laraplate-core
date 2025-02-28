<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Illuminate\Support\Facades\Log;

class FreeAllLicensesCommand extends Command
{
    protected $signature = 'auth:free-all-licenses';

    protected $description = 'Free all the assigned licenses. <comment>(⛭ Modules\Core)</comment>';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $user_class = user_class();
            $this->output->error('User class is not Modules\Core\Models\User');
            return static::SUCCESS;
        } catch (\Throwable $ex) {
            $message = $ex->getMessage();
            $this->output->error($message);
            Log::error($message);

            return static::FAILURE;
        }
    }
}
