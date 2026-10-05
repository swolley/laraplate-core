<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\IReportsVectorDimensions;

/**
 * Engine stand-in that reports a configurable indexed dimension and counts how often it is asked.
 */
final class VectorGuardEngineStub implements IReportsVectorDimensions
{
    public int $calls = 0;

    public function __construct(public ?int $dimensions = null) {}

    public function indexedVectorDimensions(Model $model): ?int
    {
        $this->calls++;

        return $this->dimensions;
    }
}
