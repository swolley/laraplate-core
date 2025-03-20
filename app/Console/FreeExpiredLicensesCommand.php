<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Illuminate\Support\Facades\Redis;

class FreeExpiredLicensesCommand extends Command
{
    protected $signature = 'auth:free-expired-licenses';

    protected $description = 'Free expired licenses. <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle()
    {
        $this->info('Freeing expired licenses...');
        user_class();
        $this->output->error('User class is not Modules\Core\Models\User');
        return static::SUCCESS;
    }
}
