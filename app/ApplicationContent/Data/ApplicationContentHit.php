<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Data;

use InvalidArgumentException;

final readonly class ApplicationContentHit
{
    private const int HARD_MAX_EXCERPT_CHARS = 8000;

    private const int HARD_MAX_LABEL_CHARS = 500;

    private const int HARD_MAX_REFERENCE_CHARS = 1000;

    public string $source;

    public string $module;

    public string $entity;

    public function __construct(
        public string $id,
        string $source,
        string $module,
        string $entity,
        public int|string $recordKey,
        public string $excerpt,
        public string $label,
        public string $canonicalReference,
        public string $locale,
        public string $strategy,
        public ?float $score,
        public ?string $revision,
        public bool $truncated,
    ) {
        $this->source = ApplicationContentSourceDescriptor::normalizeSource($source);
        $this->module = self::identifier($module);
        $this->entity = self::identifier($entity);

        $maximum_excerpt = min(
            self::HARD_MAX_EXCERPT_CHARS,
            max(1, (int) config('application-content.max_excerpt_chars', 2000)),
        );
        $maximum_label = min(
            self::HARD_MAX_LABEL_CHARS,
            max(1, (int) config('application-content.max_label_chars', 200)),
        );
        $maximum_reference = min(
            self::HARD_MAX_REFERENCE_CHARS,
            max(1, (int) config('application-content.max_reference_chars', 500)),
        );

        if (! $this->hasValidEncoding($this->id)
            || ! $this->hasValidEncoding($this->excerpt)
            || ! $this->hasValidEncoding($this->label)
            || ($this->revision !== null && ! $this->hasValidEncoding($this->revision))
            || trim($this->id) === '' || mb_strlen($this->id) > 200
            || (is_string($this->recordKey) && (! $this->hasValidEncoding($this->recordKey)
                || trim($this->recordKey) === ''
                || mb_strlen($this->recordKey) > 255))
            || trim($this->excerpt) === '' || mb_strlen($this->excerpt) > $maximum_excerpt
            || trim($this->label) === '' || mb_strlen($this->label) > $maximum_label
            || mb_strlen($this->canonicalReference) > $maximum_reference
            || preg_match('#^/app(?:/[A-Za-z0-9][A-Za-z0-9_-]*)+$#', $this->canonicalReference) !== 1
            || preg_match('/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/', $this->locale) !== 1
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $this->strategy) !== 1
            || ($this->score !== null && (! is_finite($this->score) || $this->score < 0 || $this->score > 1))
            || ($this->revision !== null && (trim($this->revision) === '' || mb_strlen($this->revision) > 200))) {
            throw new InvalidArgumentException('Application content evidence is invalid.');
        }

        $this->assertPlainText($this->excerpt);
        $this->assertPlainText($this->label);
    }

    private static function identifier(string $value): string
    {
        $value = mb_strtolower(trim($value));

        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
            throw new InvalidArgumentException('Application content evidence identifier is invalid.');
        }

        return $value;
    }

    private function assertPlainText(string $value): void
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1
            || preg_match('/<\/?[A-Za-z][^>]*>/', $value) === 1) {
            throw new InvalidArgumentException('Application content evidence must be plain text.');
        }
    }

    private function hasValidEncoding(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8');
    }
}
