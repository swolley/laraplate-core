<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

/**
 * {@see SoftDeletesStubModel} with soft deletes switched off in its own code.
 */
final class HardDeletesStubModel extends SoftDeletesStubModel
{
    protected bool $softDeletesEnabled = false;
}
