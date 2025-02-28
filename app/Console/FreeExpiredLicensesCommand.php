<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Illuminate\Support\Facades\Redis;

class FreeExpiredLicensesCommand extends Command
{
    protected $signature = 'auth:free-expired-licenses';

    protected $description = 'Free expired licenses. <comment>(⛭ Modules\Core)</comment>';

    public function handle()
    {
        $this->info('Freeing expired licenses...');

        $user_class = user_class();
        if (!$user_class instanceof \Modules\Core\Models\User) {
            $this->output->error('User class is not Modules\Core\Models\User');
            return static::SUCCESS;
        }

        $redis = Redis::connection(config('session.connection'));
        $sessions = $redis->keys('*');
        $updated = $user_class::query()->whereNotNull('license_id')->whereNotIn('license_id', $sessions)->update(['license_id' => null]);

        $this->info('Freed ' . $updated . ' expired licenses.');
    }
}
