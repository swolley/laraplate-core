<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Data;

use InvalidArgumentException;

final readonly class ApplicationContentSourceDescriptor
{
    private const array CAPABILITIES = ['hybrid', 'lexical', 'locale', 'semantic'];

    public string $source;

    public string $module;

    public string $entity;

    /**
     * @param  list<string>  $supportedLocales
     * @param  list<string>  $capabilities
     * @param  list<string>  $intentCategories
     */
    public function __construct(
        string $source,
        string $module,
        string $entity,
        public array $supportedLocales,
        public array $capabilities,
        public array $intentCategories,
    ) {
        $this->source = self::normalizeSource($source);
        $this->module = self::normalizeIdentifier($module, 'module');
        $this->entity = self::normalizeIdentifier($entity, 'entity');

        if (! array_is_list($this->supportedLocales)
            || $this->supportedLocales === []
            || count($this->supportedLocales) > 20) {
            throw new InvalidArgumentException('Application content locales are invalid.');
        }

        foreach ($this->supportedLocales as $locale) {
            if (! is_string($locale)
                || preg_match('/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/', $locale) !== 1) {
                throw new InvalidArgumentException('Application content locale is invalid.');
            }
        }

        if (! array_is_list($this->capabilities)
            || $this->capabilities === []
            || count($this->capabilities) > count(self::CAPABILITIES)) {
            throw new InvalidArgumentException('Application content capabilities are invalid.');
        }

        foreach ($this->capabilities as $capability) {
            if (! is_string($capability) || ! in_array($capability, self::CAPABILITIES, true)) {
                throw new InvalidArgumentException('Application content capability is invalid.');
            }
        }

        if (! array_is_list($this->intentCategories)
            || $this->intentCategories === []
            || count($this->intentCategories) > 20) {
            throw new InvalidArgumentException('Application content intent categories are invalid.');
        }

        foreach ($this->intentCategories as $category) {
            if (! is_string($category)
                || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $category) !== 1) {
                throw new InvalidArgumentException('Application content intent category is invalid.');
            }
        }

        if (count(array_unique($this->supportedLocales)) !== count($this->supportedLocales)
            || count(array_unique($this->capabilities)) !== count($this->capabilities)
            || count(array_unique($this->intentCategories)) !== count($this->intentCategories)) {
            throw new InvalidArgumentException('Application content descriptor values must be unique.');
        }
    }

    public static function normalizeSource(string $source): string
    {
        $source = mb_strtolower(trim($source));

        if (preg_match('/^[a-z][a-z0-9_]{0,63}\.[a-z][a-z0-9_]{0,63}$/', $source) !== 1) {
            throw new InvalidArgumentException('Application content source is invalid.');
        }

        return $source;
    }

    private static function normalizeIdentifier(string $value, string $name): string
    {
        $value = mb_strtolower(trim($value));

        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
            throw new InvalidArgumentException("Application content {$name} is invalid.");
        }

        return $value;
    }
}
