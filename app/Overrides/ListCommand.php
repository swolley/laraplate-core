<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Closure;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand as BaseListCommand;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ListCommand extends BaseListCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('internals', null, InputOption::VALUE_NONE, 'Only show commands defined outside vendor')
            ->setDescription('List commands <fg=green>(⚡ Modules\Core)</fg=green>');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $input->getOption('internals')) {
            return parent::execute($input, $output);
        }

        $helper = new DescriptorHelper();
        $helper->describe($output, $this->internalCommandsApplication(), [
            'format' => $input->getOption('format'),
            'raw_text' => $input->getOption('raw'),
            'namespace' => $input->getArgument('namespace'),
            'short' => $input->getOption('short'),
        ]);

        return self::SUCCESS;
    }

    private function internalCommandsApplication(): Application
    {
        $application = $this->getApplication();
        $commands = [];

        if ($application !== null) {
            foreach ($application->all() as $name => $command) {
                if ($this->isInternalCommand($command)) {
                    $commands[$name] = $command;
                }
            }
        }

        return new class($application, $commands) extends Application
        {
            /**
             * @param  array<string, Command>  $commands
             */
            public function __construct(?Application $source, private readonly array $commands)
            {
                parent::__construct($source?->getName() ?? 'UNKNOWN', $source?->getVersion() ?? 'UNKNOWN');
            }

            /**
             * @return array<string, Command>
             */
            #[Override]
            public function all(?string $namespace = null): array
            {
                if ($namespace === null) {
                    return $this->commands;
                }

                return array_filter(
                    $this->commands,
                    fn (Command $command, string $name): bool => $namespace === $this->extractNamespace($name, 1)
                        || ($command->getName() !== null && $namespace === $this->extractNamespace($command->getName(), 1)),
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        };
    }

    private function isInternalCommand(Command $command): bool
    {
        $file = $this->getCommandFile($command);

        if ($file === null) {
            return false;
        }

        return ! Str::startsWith(
            str_replace('\\', '/', $file),
            str_replace('\\', '/', base_path('vendor')) . '/',
        );
    }

    private function getCommandFile(Command $command): ?string
    {
        if ($command instanceof ClosureCommand) {
            return $this->getClosureCommandFile($command);
        }

        $file = (new ReflectionClass($command))->getFileName();

        return is_string($file) ? $file : null;
    }

    private function getClosureCommandFile(ClosureCommand $command): ?string
    {
        $property = new ReflectionProperty($command, 'callback');
        $callback = $property->getValue($command);

        if (! $callback instanceof Closure) {
            return null;
        }

        $file = (new ReflectionFunction($callback))->getFileName();

        return is_string($file) ? $file : null;
    }
}
