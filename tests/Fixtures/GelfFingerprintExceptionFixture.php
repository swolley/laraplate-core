<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use RuntimeException;

final class GelfFingerprintExceptionFixture
{
    public static function indexingFailure(string $detail): never
    {
        throw new RuntimeException('Indexing failed: ' . $detail);
    }
}
