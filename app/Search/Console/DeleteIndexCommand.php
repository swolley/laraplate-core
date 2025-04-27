<?php

namespace Modules\Core\Search\Console;

use Laravel\Scout\EngineManager;
use Modules\Core\Search\Traits\SearchableCommandUtils;

class DeleteIndexCommand extends \Laravel\Scout\Console\DeleteIndexCommand
{
    use SearchableCommandUtils;

    protected $signature = 'scout:delete-index {model : The model to delete the index for}';

    protected $description = 'Delete an index for a model <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[\Override]
    public function handle(EngineManager $manager)
    {
        $model = $this->getModelClass();
        if (!$model) return static::FAILURE;

        $this->addArgument('name');
        $this->input->setArgument('name', (new $model)->indexableAs());
        return parent::handle($manager);
    }
}
