<?php

declare(strict_types=1);

use Modules\Core\Tests\Stubs\Search\VectorMappingStubModel;
use Symfony\Component\Console\Command\Command as BaseCommand;

it('runs and no-ops on an engine without additive mapping sync', function (): void {
    // The database engine has no syncMapping(), so the command treats it as a
    // no-op and succeeds; only the Elasticsearch engine patches a live mapping.
    config(['scout.driver' => 'database']);

    $this->artisan('scout:sync-mapping', ['model' => VectorMappingStubModel::class])
        ->assertExitCode(BaseCommand::SUCCESS);
});
