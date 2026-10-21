<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * The codes of the aggregate contract, as the route and the assistant tool send them to a client.
 *
 * `Timeout` is reserved: the engine never emits it, so a client may already handle it. `MixedCurrency` is a
 * warning that travels with a result, not a refusal.
 */
enum AggregateErrorCode: string
{
    case InvalidField = 'INVALID_FIELD';
    case UnreadableField = 'UNREADABLE_FIELD';
    case NotMeasurable = 'NOT_MEASURABLE';
    case UnsupportedFunction = 'UNSUPPORTED_FUNCTION';
    case TooManyGroups = 'TOO_MANY_GROUPS';
    case PeriodTooLong = 'PERIOD_TOO_LONG';
    case Timeout = 'TIMEOUT';
    case MixedCurrency = 'MIXED_CURRENCY';
}
