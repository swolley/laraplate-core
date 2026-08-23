<?php

declare(strict_types=1);

namespace Modules\Core\Import\Readers;

use JsonException;
use Modules\Core\Import\Contracts\SourceReaderInterface;
use Modules\Core\Import\Support\CellStringifier;
use Override;
use RuntimeException;

/**
 * Reads a JSON document that is an array of flat objects — either at the top level
 * (`[{…},{…}]`) or under a single wrapping key (`{"data":[…]}`). Each object is one
 * row; the headers are the union of keys across every object, so rows with missing
 * keys still line up. Nested values are JSON-encoded back into a cell string.
 *
 * Unlike the streaming CSV/spreadsheet readers this decodes the whole document,
 * because a correct incremental JSON parser is a larger dependency; streaming JSON
 * is a deferred enhancement.
 */
final class JsonSourceReader implements SourceReaderInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    #[Override]
    public function headers(string $path, array $options = []): array
    {
        $headers = [];

        foreach ($this->decode($path) as $record) {
            foreach (array_keys($record) as $key) {
                $headers[(string) $key] = true;
            }
        }

        return array_keys($headers);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return iterable<int, array<string, string>>
     */
    #[Override]
    public function rows(string $path, array $options = []): iterable
    {
        foreach ($this->decode($path) as $record) {
            $row = [];

            foreach ($record as $key => $value) {
                $row[(string) $key] = is_scalar($value) || $value === null
                    ? CellStringifier::stringify($value)
                    : (string) json_encode($value);
            }

            yield $row;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decode(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read JSON import file [{$path}].");
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The JSON import file is not valid JSON.', previous: $exception);
        }

        if (is_array($decoded) && ! array_is_list($decoded)) {
            $decoded = $this->unwrap($decoded);
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException('The JSON import file must be an array of objects.');
        }

        return array_values(array_filter($decoded, static fn (mixed $record): bool => is_array($record)));
    }

    /**
     * A single wrapping key whose value is the array of records (`{"data":[…]}`).
     *
     * @param  array<string, mixed>  $decoded
     */
    private function unwrap(array $decoded): mixed
    {
        foreach ($decoded as $value) {
            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        return $decoded;
    }
}
