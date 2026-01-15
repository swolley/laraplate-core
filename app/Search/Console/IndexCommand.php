<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Override;
use Symfony\Component\Console\Command\Command;

final class IndexCommand extends \Laravel\Scout\Console\IndexCommand
{
    use HasBenchmark;
    use SearchableCommandUtils;

    protected $signature = 'scout:index {model : The model to create an index for}';

    protected $description = 'Create an index <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle(EngineManager $manager)
    {
        $model = $this->getModelClass();

        if (in_array($model, ['', '0', false], true)) {
            return Command::INVALID;
        }

        $this->addArgument('name');
        $this->input->setArgument('name', /* new $model()->indexableAs() */ $model);
        $this->addOption('key');

        parent::handle($manager);

        return Command::SUCCESS;
    }

    #[Override]
    protected function createIndex(Engine $engine, $name, $options): void
    {
        $model = $this->argument('model');
        $options = new $model()->getSearchMapping();
        parent::createIndex($engine, $name, $options);
    }
}
