<?php

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClearExpiredModels extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'model:clear-expired';

    /**
     * The console command description.
     */
    protected $description = 'Clear soft deleted models that have expired <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

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
