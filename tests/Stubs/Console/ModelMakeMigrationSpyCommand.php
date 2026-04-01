<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ModelMakeMigrationSpyCommand extends SymfonyCommand
{
    public static array $lastArguments = [];

    protected function configure(): void
    {
        $this->setName('make:migration');
        $this->addArgument('name');
        $this->addOption('create');
        $this->addOption('update');
        $this->addOption('fullpath');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        self::$lastArguments = [
            'name' => (string) $input->getArgument('name'),
            'create' => $input->getOption('create'),
            'update' => $input->getOption('update'),
            'fullpath' => (bool) $input->getOption('fullpath'),
        ];

        return 0;
    }
}
