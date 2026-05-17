<?php

declare(strict_types=1);

namespace Modules\Core\Data;

/**
 * Full LLM moderation call: neutral input plus domain-owned prompts.
 */
final readonly class ModerationRequest
{
    public function __construct(
        public ModerationInput $input,
        public string $systemPrompt,
        public string $userPrompt,
    ) {}
}
