<?php

declare(strict_types=1);

namespace Modules\Core\Support\Env;

/**
 * One `NAME=value` line of a `.env` file, active or commented out (`# NAME=value`).
 */
final readonly class EnvEntry
{
    /**
     * @param  string  $value  the value as written, without one pair of surrounding quotes
     * @param  string  $raw  the text after `=` exactly as the file has it, quotes and `${VARIABLE}` references included
     * @param  string  $comment  the comment lines directly above the entry, without their `#`, joined by a space
     */
    public function __construct(
        public string $name,
        public string $value,
        public string $raw,
        public bool $commented,
        public string $comment,
    ) {}
}
