<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use DateTimeImmutable;
use Modules\Core\Logging\GelfErrorFingerprintResolver;
use Monolog\Level;
use Monolog\LogRecord;

final class GelfFingerprintLogFixture
{
    /**
     * @return array{first: string, second: string}
     */
    public static function fingerprintsForMessages(string $first_message, string $second_message): array
    {
        $resolver = new GelfErrorFingerprintResolver;
        $fingerprints = [];

        foreach ([$first_message, $second_message] as $message) {
            $fingerprints[] = self::resolve($resolver, $message);
        }

        return [
            'first' => $fingerprints[0],
            'second' => $fingerprints[1],
        ];
    }

    private static function resolve(GelfErrorFingerprintResolver $resolver, string $message): string
    {
        return $resolver->resolve(new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'gelf',
            level: Level::Error,
            message: $message,
            context: [],
            extra: [],
        ));
    }
}
