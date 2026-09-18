<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class SwaggerUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The allow-list is empty on purpose: Swagger UI stays closed until an
        // installation names the accounts that may open it.
        Gate::define('viewSwaggerUI', static fn (?User $user = null): false => in_array($user?->getAttribute('email'), [], true));
    }
}
