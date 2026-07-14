<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Modules\Core\Search\DTOs\TextMatchOptions;

final readonly class DatabaseTextMatchCompiler
{
    /**
     * @return array{sql: string, bindings: list<float|string>, degraded: bool}
     */
    public function compile(string $driver, string $column, string $query, TextMatchOptions $options): array
    {
        $like = $options->prefix ? $query . '%' : '%' . $query . '%';
        $portable = sprintf('LOWER(%s) LIKE LOWER(?)', $column);

        if ($driver !== 'pgsql' || ! $options->typoTolerance || $options->maxEdits === 0 || mb_strlen($query) < $options->minimumTermLength) {
            return [
                'sql' => $portable,
                'bindings' => [$like],
                'degraded' => $options->typoTolerance && $options->maxEdits > 0,
            ];
        }

        return [
            'sql' => sprintf('(%s OR strict_word_similarity(LOWER(?), LOWER(CAST(%s AS TEXT))) >= ?)', $portable, $column),
            'bindings' => [$like, $query, $options->similarityThreshold],
            'degraded' => false,
        ];
    }
}
