<?php

declare(strict_types=1);

namespace Modules\Core\Search\Exceptions;

use Exception;

/**
 * Unwinds a {@see \Modules\Core\Search\DeferredSearchIndexing} run stopped on
 * request (an import interrupted with Ctrl+C), so the run indexes what it
 * recorded on its way out.
 *
 * Deliberately not a RuntimeException: domain code that turns runtime
 * failures into row errors must not swallow a stop.
 */
final class DeferredRunInterruptedException extends Exception {}
