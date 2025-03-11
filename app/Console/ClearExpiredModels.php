<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ClearExpiredModels extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'model:clear-expired';

    /**
     * The console command description.
     */
    protected $description = 'Clear soft deleted models that have expired <comment>(⛭ Modules\Core)</comment>';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expirationDays = config('core.soft_deletes_expiration_days');

        if ($expirationDays) {
            foreach (models() as $model) {
                if (class_uses_trait($model, SoftDeletes::class)) {
                    $model::onlyTrashed()->where('deleted_at', '<', now()->subDays($expirationDays))->forceDelete();
                }
            }
        }
    }
}
