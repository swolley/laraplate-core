<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * Model whose search engine is injected through a static, for the vector availability guard tests.
 */
final class VectorGuardStubModel extends Model
{
    public static ?object $engine = null;

    public function searchableUsing(): ?object
    {
        return self::$engine;
    }
}
