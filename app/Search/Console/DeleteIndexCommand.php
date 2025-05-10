<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Override;
use Laravel\Scout\EngineManager;
use Modules\Core\Helpers\HasBenchmark;
use Symfony\Component\Console\Command\Command;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Laravel\Scout\Console\DeleteIndexCommand as BaseDeleteIndexCommand;

final class DeleteIndexCommand extends BaseDeleteIndexCommand
{
    use HasBenchmark, SearchableCommandUtils;

    protected $signature = 'scout:delete-index {model : The model to delete the index for}';

    protected $description = 'Delete an index for a model <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle(EngineManager $manager)
    {
        $model = $this->getModelClass();

        if ($model === '' || $model === '0' || $model === false) {
            return Command::INVALID;
        }

        $this->addArgument('name');
        $this->input->setArgument('name', new $model()->indexableAs());

        parent::handle($manager);

        return Command::SUCCESS;
    }
}
