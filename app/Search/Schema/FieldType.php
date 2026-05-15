<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

use DateTimeInterface;
use Modules\Core\Inspector\Types\DoctrineTypeEnum;

enum FieldType: string
{
    case Text = 'text';
    case Keyword = 'keyword';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Date = 'date';
    case Vector = 'vector';
    case Array = 'array';
    case Object = 'object';
    case Geocode = 'geocode';

    public static function fromDoctrine(DoctrineTypeEnum $type): self
    {
        return match ($type) {
            DoctrineTypeEnum::Json => self::Object,
            DoctrineTypeEnum::Bigint, DoctrineTypeEnum::Integer, DoctrineTypeEnum::Smallint, DoctrineTypeEnum::Decimal => self::Integer,
            DoctrineTypeEnum::Binary, DoctrineTypeEnum::Boolean => self::Boolean,
            DoctrineTypeEnum::Date, DoctrineTypeEnum::DateImmutable, DoctrineTypeEnum::Dateinterval, DoctrineTypeEnum::Datetime, DoctrineTypeEnum::DatetimeImmutable, DoctrineTypeEnum::Datetimetz, DoctrineTypeEnum::DatetimetzImmutable, DoctrineTypeEnum::Time, DoctrineTypeEnum::TimeImmutable => self::Date,
            DoctrineTypeEnum::Float => self::Float,
            DoctrineTypeEnum::SimpleArray => self::Array,
            DoctrineTypeEnum::Geometry => self::Geocode,
            // TODO: what aboud json? Are object or arrays?
            default => self::tryFrom($type->value) ?: self::Text,
        };
    }

    public static function fromValue(mixed $value): self
    {
        if (($value instanceof DateTimeInterface)) {
            return self::Date;
        }

        $type = gettype($value);

        return match ($type) {
            'double' => self::Float,
            'string' => self::Text,
            'geometry' => self::Geocode,
            // TODO: arrays or objects must be declared as keywords?
            default => self::tryFrom($type) ?: self::Text,
        };
    }
}
