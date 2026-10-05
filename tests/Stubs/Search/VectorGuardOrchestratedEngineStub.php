<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Mockery;
use Modules\Core\Search\Contracts\IReportsVectorDimensions;
use Modules\Core\Search\Contracts\ISearchEngine;

/**
 * Orchestrated engine stand-in that reports a configurable indexed dimension.
 */
final class VectorGuardOrchestratedEngineStub
{
    public static function make(?int $dimensions): ISearchEngine&IReportsVectorDimensions
    {
        $engine = Mockery::mock(ISearchEngine::class, IReportsVectorDimensions::class);
        $engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
        $engine->shouldReceive('supportsOrchestratedVectorSearch')->andReturnTrue();
        $engine->shouldReceive('indexedVectorDimensions')->andReturn($dimensions);

        return $engine;
    }
}
