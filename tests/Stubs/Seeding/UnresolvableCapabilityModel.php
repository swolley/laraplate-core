<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeding;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately abstract: `ReflectionClass::newInstanceWithoutConstructor()`
 * throws "Cannot instantiate abstract class" for it, exercising
 * ModelCapabilityScanner's skip-and-log path for a model that fails to
 * resolve.
 */
abstract class UnresolvableCapabilityModel extends Model {}
