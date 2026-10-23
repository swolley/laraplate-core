<?php

declare(strict_types=1);

namespace Modules\Core\Support\Env;

/**
 * How a value is written after `NAME=` in a `.env` file, and which names hold secrets.
 */
final readonly class EnvValue
{
    /**
     * The characters Dotenv reads literally when a value is not quoted; an empty value is written bare too (`NAME=`).
     */
    private const string BARE_PATTERN = '/^[A-Za-z0-9_.:\/@%+,-]*$/';

    /**
     * The last underscore-separated word of a name that marks it as holding a secret.
     *
     * @var list<string>
     */
    private const array SECRET_WORDS = ['KEY', 'SECRET', 'PASSWORD', 'PASS', 'TOKEN', 'CREDENTIAL'];

    /**
     * The text to put after `NAME=`: the value itself when Dotenv reads it literally (an empty value included), otherwise wrapped in
     * double quotes with the backslash, the double quote and the dollar escaped and line breaks written as `\n`.
     */
    public static function format(string $value): string
    {
        if (preg_match(self::BARE_PATTERN, $value) === 1) {
            return $value;
        }

        $escaped = str_replace(['\\', '"', '$', "\r\n", "\n", "\r"], ['\\\\', '\\"', '\\$', '\\n', '\\n', '\\r'], $value);

        return '"' . $escaped . '"';
    }

    /**
     * Whether a variable of this name holds a secret (`API_KEY`, `DB_PASSWORD`, `GITHUB_CLIENT_SECRET`):
     * its last underscore-separated word is one of the secret words.
     */
    public static function isSecretName(string $name): bool
    {
        $words = explode('_', mb_strtoupper($name));

        return in_array(end($words), self::SECRET_WORDS, true);
    }
}
