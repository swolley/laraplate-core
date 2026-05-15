<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum FilterOperator: string
{
    case Great = '>';
    case GreatEquals = '>=';
    case Less = '<';
    case LessEquals = '<=';
    case Like = 'like';
    case NotLike = 'not like';
    case Equals = '=';
    case In = 'in';
    case NotEquals = '!=';
    case Between = 'between';

    public static function tryFromRequestOperator(RequestFilterOperator|string $operator): ?self
    {
        return match ($operator) {
            RequestFilterOperator::Great, RequestFilterOperator::Great->value => self::Great,
            RequestFilterOperator::GreatEquals, RequestFilterOperator::GreatEquals->value => self::GreatEquals,
            RequestFilterOperator::Less, RequestFilterOperator::Less->value => self::Less,
            RequestFilterOperator::LessEquals, RequestFilterOperator::LessEquals->value => self::LessEquals,
            RequestFilterOperator::Like, RequestFilterOperator::Like->value => self::Like,
            RequestFilterOperator::NotLike, RequestFilterOperator::NotLike->value => self::NotLike,
            RequestFilterOperator::Equals, RequestFilterOperator::Equals->value => self::Equals,
            RequestFilterOperator::In, RequestFilterOperator::In->value => self::In,
            RequestFilterOperator::NotEquals, RequestFilterOperator::NotEquals->value => self::NotEquals,
            RequestFilterOperator::Between, RequestFilterOperator::Between->value => self::Between,
            default => null,
        };
    }
}
