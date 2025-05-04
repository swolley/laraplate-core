<?php

namespace Modules\Core\Search\Console;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Modules\Core\Helpers\HasBenchmark;
use Symfony\Component\Console\Command\Command;

class IndexCommand extends \Laravel\Scout\Console\IndexCommand
{
    use SearchableCommandUtils, HasBenchmark;

    protected $signature = 'scout:index {model : The model to create an index for}';

    protected $description = 'Create an index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[\Override]
    public function handle(EngineManager $manager)
    {
        $model = $this->getModelClass();
        if (!$model) {
            $this->error('Model not found');
            return Command::FAILURE;
        }

        $this->addArgument('name');
        $this->input->setArgument('name', (new $model())->indexableAs());
        $this->addOption('key');
        return parent::handle($manager);
    }

    #[\Override]
    protected function createIndex(Engine $engine, $name, $options): void
    {
        $model = $this->argument('model');
        $options = (new $model())->getSearchMapping();
        parent::createIndex($engine, $name, $options);
    }
}
