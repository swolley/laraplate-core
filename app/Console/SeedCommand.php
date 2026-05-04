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
    public function handle(): int
    {
        if ($this->option('dev')) {
            return (int) $this->call('db:seed', ['--class' => DevDatabaseSeeder::class]);
        }

        return parent::handle();
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string, 4?: mixed}>
     */
    #[Override]
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            ['dev', null, InputOption::VALUE_NONE, 'Seed the database with development data'],
        ]);
    }
}