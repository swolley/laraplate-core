<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

use Modules\Core\Inspector\Types\DoctrineTypeEnum;

enum FieldType: string
{
    case TEXT = 'text';
    case KEYWORD = 'keyword';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case VECTOR = 'vector';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case GEOCODE = 'geocode';

    public static function fromDoctrine(DoctrineTypeEnum $type): self
    {
        return match ($type) {
            DoctrineTypeEnum::JSON => self::OBJECT,
            DoctrineTypeEnum::BIGINT, DoctrineTypeEnum::INTEGER, DoctrineTypeEnum::SMALLINT, DoctrineTypeEnum::DECIMAL => self::INTEGER,
            DoctrineTypeEnum::BINARY, DoctrineTypeEnum::BOOLEAN => self::BOOLEAN,
            DoctrineTypeEnum::DATE, DoctrineTypeEnum::DATE_IMMUTABLE, DoctrineTypeEnum::DATEINTERVAL, DoctrineTypeEnum::DATETIME, DoctrineTypeEnum::DATETIME_IMMUTABLE, DoctrineTypeEnum::DATETIMETZ, DoctrineTypeEnum::DATETIMETZ_IMMUTABLE, DoctrineTypeEnum::TIME, DoctrineTypeEnum::TIME_IMMUTABLE => self::DATE,
            DoctrineTypeEnum::FLOAT => self::FLOAT,
            DoctrineTypeEnum::SIMPLE_ARRAY => self::ARRAY,
            DoctrineTypeEnum::GEOMETRY => self::GEOCODE,
            // TODO: what aboud json? Are object or arrays?
            default => self::tryFrom($type->value) ?: self::TEXT,
        };
    }

    public static function fromValue(mixed $value): self
    {
        if (($value instanceof \DateTimeInterface)) {
            return self::DATE;
        }

        $type = gettype($value);
        return match ($type) {
            'double' => self::FLOAT,
            'string' => self::TEXT,
            'geometry' => self::GEOCODE,
            // TODO: arrays or objects must be declared as keywords?
            default => self::tryFrom($type) ?: self::TEXT,
        };
    }
}
