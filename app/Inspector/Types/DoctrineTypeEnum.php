<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Types;

use Doctrine\DBAL\Types\AsciiStringType;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\Type as DoctrineType;
use MatanYadaev\EloquentSpatial\Doctrine\GeometryType;

enum DoctrineTypeEnum: string
{
    case AsciiString = 'ascii_string';
    case Bigint = 'bigint';
    case Binary = 'binary';
    case Blob = 'blob';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateImmutable = 'date_immutable';
    case Dateinterval = 'dateinterval';
    case Datetime = 'datetime';
    case DatetimeImmutable = 'datetime_immutable';
    case Datetimetz = 'datetimetz';
    case DatetimetzImmutable = 'datetimetz_immutable';
    case Decimal = 'decimal';
    case Float = 'float';
    case Guid = 'guid';
    case Integer = 'integer';
    case Json = 'json';
    case SimpleArray = 'simple_array';
    case Smallint = 'smallint';
    case String = 'string';
    case Text = 'text';
    case Time = 'time';
    case TimeImmutable = 'time_immutable';
    case Unknown = 'unknown';
    case Geometry = 'geometry';

    public static function fromDoctrine(DoctrineType|self $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        return self::fromDoctrineType($type);
    }

    public static function fromString(string $type): self
    {
        return self::tryFrom($type) ?: self::Unknown;
    }

    private static function fromDoctrineType(DoctrineType|GeometryType $type): self
    {
        /**
         * This variable exists only so PHPStan
         * can treat it as a regular string and not a
         * `class-string<Doctrine\DBAL\Types\Type>`.
         *
         * Otherwise PHPStan trips over the types not
         * directy extending `Doctrine\DBAL\Types\Type`.
         */
        $class = $type::class;

        return match ($class) {
            AsciiStringType::class => self::AsciiString,
            BigIntType::class => self::Bigint,
            BinaryType::class => self::Binary,
            BlobType::class => self::Blob,
            BooleanType::class => self::Boolean,
            DateType::class => self::Date,
            DateImmutableType::class => self::DateImmutable,
            DateIntervalType::class => self::Dateinterval,
            DateTimeType::class => self::Datetime,
            DateTimeImmutableType::class => self::DatetimeImmutable,
            DateTimeTzType::class => self::Datetimetz,
            DateTimeTzImmutableType::class => self::DatetimetzImmutable,
            DecimalType::class => self::Decimal,
            FloatType::class => self::Float,
            GuidType::class => self::Guid,
            IntegerType::class => self::Integer,
            JsonType::class => self::Json,
            SimpleArrayType::class => self::SimpleArray,
            SmallIntType::class => self::Smallint,
            StringType::class => self::String,
            TextType::class => self::Text,
            TimeType::class => self::Time,
            TimeImmutableType::class => self::TimeImmutable,
            GeometryType::class => self::Geometry,
            default => self::Unknown,
        };
    }

    // public function raw(): ?string
    // {
    //     if ($this->value === self::Unknown->value) {
    //         return null;
    //     }

    //     return $this->value;
    // }
}
