<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Override;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Modules\Core\Helpers\HasBenchmark;
use Symfony\Component\Console\Command\Command;
use Modules\Core\Search\Traits\SearchableCommandUtils;

final class IndexCommand extends \Laravel\Scout\Console\IndexCommand
{
    use HasBenchmark, SearchableCommandUtils;

    protected $signature = 'scout:index {model : The model to create an index for}';

    protected $description = 'Create an index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle(EngineManager $manager)
    {
        $model = $this->getModelClass();

        if ($model === '' || $model === '0' || $model === false) {
            $this->error('Model not found');

            return Command::INVALID;
        }

        $this->addArgument('name');
        $this->input->setArgument('name', new $model()->indexableAs());
        $this->addOption('key');

        return parent::handle($manager);
    }

    #[Override]
    protected function createIndex(Engine $engine, $name, $options): void
    {
        $model = $this->argument('model');
        $options = new $model()->getSearchMapping();
        parent::createIndex($engine, $name, $options);
    }
}
