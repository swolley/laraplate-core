<?php

declare(strict_types=1);

namespace Modules\Core\Data;

/**
 * Domain-neutral moderation payload assembled by a {@see \Modules\Core\Contracts\ModerationAdapter}.
 */
final readonly class ModerationInput
{
    /**
     * @param  array<string, string>  $contextSections  Labeled context blocks (e.g. article excerpt, parent comment)
     */
    public function __construct(
        public string $subjectText,
        public string $locale,
        public array $contextSections = [],
        public string $profile = 'default',
    ) {}
}
