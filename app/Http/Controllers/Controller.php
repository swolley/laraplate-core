<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Auth\AuthManager;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Controller as RoutingController;

abstract class Controller extends RoutingController
{
    public function __construct(
        protected Repository $cache,
        protected AuthManager $auth,
        protected DatabaseManager $db
    ) {}
}
