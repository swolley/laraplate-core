<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

/**
 * Profiles an HTTP endpoint by dispatching real requests through the HTTP
 * kernel, so the measured cost includes the full middleware, routing,
 * controller, authorization and serialization stack — not just a controller
 * method in isolation.
 */
final class EndpointProfiler
{
    public function __construct(
        private readonly HttpKernel $kernel,
        private readonly BenchmarkRunner $runner,
        private readonly AuthFactory $auth,
    ) {}

    public function profile(string $method, string $uri, int $iterations = 30, int $warmup = 3, ?Authenticatable $user = null): EndpointBenchmarkReport
    {
        $method = mb_strtoupper($method);
        $last_status = 0;

        // Authenticate on the guard, not the request: the kernel rebinds the
        // "request" instance on every handle() and resets its user resolver to
        // the guard-backed one, so a per-request resolver would be discarded.
        if ($user !== null) {
            $this->auth->guard()->setUser($user);
        }

        $benchmark = $this->runner->run(function () use ($method, $uri, &$last_status): void {
            $request = Request::create($uri, $method);
            $response = $this->kernel->handle($request);
            $last_status = $response->getStatusCode();
        }, $iterations, $warmup);

        return new EndpointBenchmarkReport($method, $uri, $last_status, $benchmark);
    }
}
