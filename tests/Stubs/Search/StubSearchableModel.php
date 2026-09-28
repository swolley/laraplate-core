<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * A minimal Eloquent model used to exercise the searchable-contributor registry
 * without touching the database.
 */
final class StubSearchableModel extends Model {}
