<?php

declare(strict_types=1);

namespace Modules\Core\Events;

/**
 * A request for optional AI-generated text, dispatched synchronously with a
 * mutable response slot.
 *
 * It is the seam that lets a module ask an AI for text without depending on the
 * AI module: the requester dispatches this event and reads {@see $response}
 * afterwards; an AI listener, when the feature is enabled, fills it. If nothing
 * listens the response stays null and the requester falls back to its own
 * deterministic behaviour — the same optional-handler pattern as
 * {@see ModelRequiresIndexing}. The event lives in Core so both the requester
 * and the AI module depend only on Core, never on each other.
 *
 * Because a listener must be able to mutate and return the response inline, this
 * event is always handled synchronously; a listener must not queue it.
 */
final class AiTextGenerationRequested
{
    /**
     * The generated text, set by a listener. Null (or empty) means unfulfilled.
     */
    public ?string $response = null;

    /**
     * @param  string  $prompt  The instruction the AI should act on.
     * @param  string  $purpose  A stable key identifying the caller/use (e.g. `sao.ownership_suggestion`), so a listener can scope or route.
     * @param  array<string, mixed>  $context  Optional structured context for the listener.
     */
    public function __construct(
        public readonly string $prompt,
        public readonly string $purpose,
        public readonly array $context = [],
    ) {}

    public function fulfill(string $response): void
    {
        $this->response = $response;
    }

    public function isFulfilled(): bool
    {
        return $this->response !== null && $this->response !== '';
    }
}
