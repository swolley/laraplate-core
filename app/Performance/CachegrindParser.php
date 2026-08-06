<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

use RuntimeException;

/**
 * Streaming parser for Xdebug cachegrind profiles.
 *
 * Accumulates the self cost (time spent in a function's own statements, not in
 * the functions it calls) per function, so the hottest leaves of a request are
 * surfaced. Handles Xdebug's compressed "(id) Name" reference form where a name
 * is declared once and referenced by id thereafter.
 */
final class CachegrindParser
{
    public function summarize(string $path, int $limit = 30): CachegrindSummary
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to read cachegrind file: %s', $path));
        }

        /** @var array<int|string, string> $names */
        $names = [];
        /** @var array<int|string, int> $self */
        $self = [];
        $current = null;
        $pending_call = false;

        while (($line = fgets($handle)) !== false) {
            if (str_starts_with($line, 'fn=')) {
                [$current, $name] = $this->parseReference(mb_substr($line, 3));

                if ($name !== null) {
                    $names[$current] = $name;
                }

                $pending_call = false;

                continue;
            }

            if (str_starts_with($line, 'cfn=')) {
                [$id, $name] = $this->parseReference(mb_substr($line, 4));

                if ($name !== null) {
                    $names[$id] = $name;
                }

                continue;
            }

            if (str_starts_with($line, 'calls=')) {
                $pending_call = true;

                continue;
            }

            if ($current !== null && preg_match('/^-?\d+\s+(\d+)/', $line, $matches) === 1) {
                if ($pending_call) {
                    $pending_call = false;

                    continue;
                }

                $self[$current] = ($self[$current] ?? 0) + (int) $matches[1];
            }
        }

        fclose($handle);

        return $this->rank($names, $self, $limit);
    }

    /**
     * Parse a "(id) Name" / "(id)" / "Name" reference into [key, name|null].
     *
     * @return array{0:int|string,1:string|null}
     */
    private function parseReference(string $raw): array
    {
        $raw = mb_trim($raw);

        if (preg_match('/^\((\d+)\)(?:\s+(.*))?$/', $raw, $matches) === 1) {
            $name = ($matches[2] ?? '') === '' ? null : $matches[2];

            return [(int) $matches[1], $name];
        }

        return ['name:' . $raw, $raw];
    }

    /**
     * @param  array<int|string, string>  $names
     * @param  array<int|string, int>  $self
     */
    private function rank(array $names, array $self, int $limit): CachegrindSummary
    {
        $total = array_sum($self);
        arsort($self);

        $functions = [];
        $count = 0;

        foreach ($self as $key => $cost) {
            $functions[] = new FunctionCost(
                name: $names[$key] ?? ('#' . $key),
                self: $cost,
                percent: $total > 0 ? round(100 * $cost / $total, 1) : 0.0,
            );

            if (++$count >= $limit) {
                break;
            }
        }

        return new CachegrindSummary(totalSelf: $total, functions: $functions);
    }
}
