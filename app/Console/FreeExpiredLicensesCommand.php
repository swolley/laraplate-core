<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Command;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class FreeExpiredLicensesCommand extends Command
{
    #[Override]
    protected $signature = 'auth:free-expired-licenses';

    #[Override]
    protected $description = 'Free expired licenses. <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        $this->info('Freeing expired licenses...');
        $user_class = user_class();

        if ($user_class !== User::class && ! is_subclass_of($user_class, User::class)) {
            $this->output->error('User class is not ' . User::class);

            return BaseCommand::FAILURE;
        }

        $expired_license_ids = License::query()->expired()->pluck('id');
        $user_class::query()->whereIn('license_id', $expired_license_ids)->update(['license_id' => null]);

        $this->output->success('Expired licenses have been freed');

        return BaseCommand::SUCCESS;
    }
}
