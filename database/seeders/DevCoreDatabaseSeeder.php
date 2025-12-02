<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Helpers\BatchSeeder;
use Modules\Core\Models\License;
use Modules\Core\Models\User;

final class DevCoreDatabaseSeeder extends BatchSeeder
{
    private const TARGET_COUNT_USERS = 6000;

    private const TARGET_COUNT_LICENSES = 2000;

    protected function execute(): void
    {
        Artisan::call('module:seed', ['module' => 'Core', '--force' => $this->command->option('force')], outputBuffer: $this->command->getOutput());

        Model::unguarded(function (): void {
            $this->seedUsers();
            $this->seedLicenses();
        });

        Artisan::call('cache:clear');
    }

    private function seedUsers(): void
    {
        $this->createInBatches(User::class, self::TARGET_COUNT_USERS);
    }

    private function seedLicenses(): void
    {
        $this->createInBatches(License::class, self::TARGET_COUNT_LICENSES);
    }
}
