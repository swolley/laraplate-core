<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Override;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    #[Override]
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user): bool => $user && $user instanceof \Modules\Core\Models\User && $user->isSuperAdmin());
    }
}
