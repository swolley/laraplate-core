<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum RequestFilterOperator: string
{
    case Great = 'gt';
    case GreatEquals = 'ge';
    case Less = 'lt';
    case LessEquals = 'le';
    case Like = 'like';
    case NotLike = 'not like';
    case Equals = 'eq';
    case In = 'in';
    case NotEquals = 'ne';
    case Between = 'between';

    public static function tryFromFilterOperator(FilterOperator $operator): ?static
    {
        return match ($operator) {
            FilterOperator::Great => self::Great,
            FilterOperator::GreatEquals => self::GreatEquals,
            FilterOperator::Less => self::Less,
            FilterOperator::LessEquals => self::LessEquals,
            FilterOperator::Like => self::Like,
            FilterOperator::NotLike => self::NotLike,
            FilterOperator::Equals => self::Equals,
            FilterOperator::In => self::In,
            FilterOperator::NotEquals => self::NotEquals,
            FilterOperator::Between => self::Between,
        };
    }
}
