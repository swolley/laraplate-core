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

    private const string ACTIVE_PATTERN = '/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)=(.*)$/';

    /**
     * A commented variable is `# NAME=` followed by a quoted text or one token; anything longer is prose that
     * happens to start like one (`# SAO_SYNC_ENABLED=false to keep the command manual-only.`).
     */
    private const string COMMENTED_PATTERN = '/^\s*#\s*([A-Z][A-Z0-9_]*)=("[^"]*"|\'[^\']*\'|\S*)\s*$/';

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
            if (preg_match(self::ACTIVE_PATTERN, $line, $match) === 1) {
                $raw = mb_trim($match[2]);
                $entries[] = new EnvEntry($match[1], self::unquote($raw), $raw, false, implode(' ', $comment));
                $comment = [];

                continue;
            }

            if (preg_match(self::COMMENTED_PATTERN, $line, $match) === 1) {
                $entries[] = new EnvEntry($match[1], self::unquote($match[2]), $match[2], true, implode(' ', $comment));
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
        return $this->namesDefinedIn($this->content);
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
     * Appends `NAME=value`, the value quoted when Dotenv would read it differently. Nothing else of the file
     * changes: see {@see self::write()}.
     *
     * @throws InvalidArgumentException when the name is not NAME-shaped or is already defined
     * @throws RuntimeException when the file or its directory cannot be written
     */
    public function append(string $name, string $value): void
    {
        $this->write($name, EnvValue::format($value));
    }

    /**
     * Appends `NAME=` followed by `$text` exactly as given (an example's `"${APP_NAME}"` keeps its quotes and its
     * reference), where {@see self::append()} would escape the `$`.
     *
     * @throws InvalidArgumentException when the name is not NAME-shaped or is already defined
     * @throws RuntimeException when the file or its directory cannot be written
     */
    public function appendRaw(string $name, string $text): void
    {
        $this->write($name, $text);
    }

    private static function unquote(string $value): string
    {
        if (mb_strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && str_ends_with($value, $value[0])) {
            return mb_substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * The one write: it works on what the file holds now, not on what it held when it was read, so an edit made
     * meanwhile is kept; it follows a symlink to the file it points to; the new content goes to a temporary file in
     * the same directory, which takes the mode (and, for a superuser, the owner and group) of the original and
     * replaces it, so a crash never leaves a truncated file. Every filesystem call is checked, not left to raise
     * a PHP warning: a warning would escape as an exception whose trace holds the value.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function write(string $name, string $text): void
    {
        throw_if(preg_match(self::NAME_PATTERN, $name) !== 1, InvalidArgumentException::class, 'A variable name is upper case letters, digits and underscores, and starts with a letter.');

        $target = realpath($this->path) ?: $this->path;
        $directory = dirname($target);

        throw_unless(is_file($target) && is_writable($target) && is_writable($directory), RuntimeException::class, "The file {$this->path} cannot be written.");

        $current = @file_get_contents($target);

        throw_if($current === false, RuntimeException::class, "The file {$this->path} cannot be read.");
        throw_if(in_array($name, $this->namesDefinedIn($current), true), InvalidArgumentException::class, "{$name} is already defined in the file.");

        $eol = str_contains($current, "\r\n") ? "\r\n" : "\n";
        $content = $current;

        if ($content !== '' && ! str_ends_with($content, "\n") && ! str_ends_with($content, "\r")) {
            $content .= $eol;
        }

        $content .= $name . '=' . $text . $eol;

        $temporary = @tempnam($directory, '.env.');

        throw_if($temporary === false, RuntimeException::class, "The file {$this->path} cannot be written.");

        $mode = @fileperms($target);
        $written = @file_put_contents($temporary, $content) === mb_strlen($content)
            && @chmod($temporary, $mode === false ? 0o600 : $mode & 0o777)
            && $this->keepOwner($temporary, $target)
            && @rename($temporary, $target);

        if (! $written) {
            @unlink($temporary);

            throw new RuntimeException("The file {$this->path} cannot be written.");
        }

        $this->content = $content;
    }

    /**
     * Gives the temporary file the owner and group of the file it replaces, which only a superuser may do: a
     * `sudo php artisan env:fill` must not hand a www-data `.env` to root. Not being able to is not a failure.
     */
    private function keepOwner(string $temporary, string $target): bool
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $owner = @fileowner($target);
            $group = @filegroup($target);

            if ($owner !== false) {
                @chown($temporary, $owner);
            }

            if ($group !== false) {
                @chgrp($temporary, $group);
            }
        }

        return true;
    }

    /**
     * @throws RuntimeException when the content is not valid dotenv syntax; the message never carries a value
     *
     * @return list<string>
     */
    private function namesDefinedIn(string $content): array
    {
        try {
            return array_map(strval(...), array_keys(Dotenv::parse($content)));
        } catch (InvalidFileException) {
            throw new RuntimeException("The file {$this->path} is not valid dotenv syntax.");
        }
    }
}
