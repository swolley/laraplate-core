<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

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
    ) {}

    public function profile(string $method, string $uri, int $iterations = 30, int $warmup = 3): EndpointBenchmarkReport
    {
        $method = mb_strtoupper($method);
        $last_status = 0;

        $benchmark = $this->runner->run(function () use ($method, $uri, &$last_status): void {
            $request = Request::create($uri, $method);
            $response = $this->kernel->handle($request);
            $last_status = $response->getStatusCode();
        }, $iterations, $warmup);

        return new EndpointBenchmarkReport($method, $uri, $last_status, $benchmark);
    }
}
