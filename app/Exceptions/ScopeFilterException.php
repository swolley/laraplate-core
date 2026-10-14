<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use InvalidArgumentException;

/**
 * A scope filter node refused by {@see \Modules\Core\Support\ScopeGuard}: the scope is not declared, is ACL-only
 * in a client request, its parameters do not fit, or it does more than add where conditions.
 *
 * A subclass of InvalidArgumentException, so it fails closed wherever an invalid filter does, and a class of its
 * own, so a caller can tell a guard refusal from an InvalidArgumentException the scope throws itself. The message
 * names the scope, the model and the reason, never a parameter value.
 */
final class ScopeFilterException extends InvalidArgumentException {}
