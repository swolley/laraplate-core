<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * A second unrelated model used to assert a contributor does not leak onto other
 * model classes.
 */
final class StubOtherSearchableModel extends Model {}
