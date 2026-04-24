<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Database\Seeders\DevDatabaseSeeder;
use Illuminate\Database\Console\Seeds\SeedCommand as BaseSeedCommand;
use Override;
use Symfony\Component\Console\Input\InputOption;

final class SeedCommand extends BaseSeedCommand
{
    protected $description = 'Seed the database with records. <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle()
    {
        if ($this->option('dev')) {
            $this->call('db:seed', ['--class' => DevDatabaseSeeder::class]);
        } else {
            parent::handle();
        }
    }

    #[Override]
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['dev', null, InputOption::VALUE_NONE, 'Seed the database with development data'],
        ]);
    }
}