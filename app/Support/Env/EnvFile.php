<?php

declare(strict_types=1);

namespace Modules\Core\Support\Env;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use InvalidArgumentException;
use RuntimeException;

/**
 * A `.env` file (or an example of one): which variables it defines, which of the ones in an example it
 * lacks, and the one write it supports, appending a variable. The rest of the file is never rewritten.
 */
final class EnvFile
{
    private const string NAME_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    private const string ENTRY_PATTERN = '/^\s*(#\s*)?([A-Z][A-Z0-9_]*)=(.*)$/';

    private function __construct(
        private readonly string $path,
        private string $content,
    ) {}

    /**
     * @throws RuntimeException when the file cannot be read
     */
    public static function read(string $path): self
    {
        $content = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        throw_if($content === false, RuntimeException::class, "The file {$path} cannot be read.");

        return new self($path, $content);
    }

    /**
     * Every `NAME=` and `# NAME=` line, in file order. The comment lines directly above an entry (up to a
     * blank line or another entry) are its comment; a comment that is not a `NAME=` line is prose.
     *
     * @return list<EnvEntry>
     */
    public function entries(): array
    {
        $entries = [];
        $comment = [];

        foreach (preg_split('/\r\n|\n|\r/', $this->content) ?: [] as $line) {
            if (preg_match(self::ENTRY_PATTERN, $line, $match) === 1) {
                $entries[] = new EnvEntry($match[2], self::unquote(mb_trim($match[3])), $match[1] !== '', implode(' ', $comment));
                $comment = [];

                continue;
            }

            if (preg_match('/^\s*#\s*(.*)$/', $line, $match) === 1) {
                $comment[] = mb_trim($match[1]);

                continue;
            }

            $comment = [];
        }

        return $entries;
    }

    /**
     * The names with an active line. Read by Dotenv, which knows multi-line quoted values.
     *
     * @throws RuntimeException when the file is not valid dotenv syntax; the message never carries a value
     *
     * @return list<string>
     */
    public function definedNames(): array
    {
        try {
            return array_map(strval(...), array_keys(Dotenv::parse($this->content)));
        } catch (InvalidFileException) {
            throw new RuntimeException("The file {$this->path} is not valid dotenv syntax.");
        }
    }

    public function entry(string $name): ?EnvEntry
    {
        $found = null;

        foreach ($this->entries() as $entry) {
            if ($entry->name !== $name) {
                continue;
            }

            if (! $entry->commented) {
                return $entry;
            }

            $found ??= $entry;
        }

        return $found;
    }

    /**
     * The names of `$example`, active or commented, in its order and once each, that this file does not define.
     *
     * @return list<string>
     */
    public function missingFrom(self $example): array
    {
        $defined = array_flip($this->definedNames());
        $missing = [];

        foreach ($example->entries() as $entry) {
            if (! isset($defined[$entry->name]) && ! in_array($entry->name, $missing, true)) {
                $missing[] = $entry->name;
            }
        }

        return $missing;
    }

    /**
     * Appends `NAME=value` and nothing else changes: the new content goes to a temporary file in the same
     * directory, takes the mode of the original and replaces it, so a crash never leaves a truncated file.
     *
     * @throws InvalidArgumentException when the name is not NAME-shaped or is already defined
     * @throws RuntimeException when the file or its directory cannot be written
     */
    public function append(string $name, string $value): void
    {
        throw_if(preg_match(self::NAME_PATTERN, $name) !== 1, InvalidArgumentException::class, 'A variable name is upper case letters, digits and underscores, and starts with a letter.');
        throw_if(in_array($name, $this->definedNames(), true), InvalidArgumentException::class, "{$name} is already defined in the file.");

        $directory = dirname($this->path);

        throw_unless(is_writable($this->path) && is_writable($directory), RuntimeException::class, "The file {$this->path} cannot be written.");

        $eol = str_contains($this->content, "\r\n") ? "\r\n" : "\n";
        $content = $this->content;

        if ($content !== '' && ! str_ends_with($content, "\n") && ! str_ends_with($content, "\r")) {
            $content .= $eol;
        }

        $content .= $name . '=' . EnvValue::format($value) . $eol;

        $temporary = tempnam($directory, '.env.');

        throw_if($temporary === false, RuntimeException::class, "The file {$this->path} cannot be written.");

        $mode = fileperms($this->path);

        if (file_put_contents($temporary, $content) === false || ! chmod($temporary, $mode === false ? 0o600 : $mode & 0o777) || ! rename($temporary, $this->path)) {
            @unlink($temporary);

            throw new RuntimeException("The file {$this->path} cannot be written.");
        }

        $this->content = $content;
    }

    private static function unquote(string $value): string
    {
        if (mb_strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && str_ends_with($value, $value[0])) {
            return mb_substr($value, 1, -1);
        }

        return $value;
    }
}
