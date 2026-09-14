<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event emitted when a single locale of a translated model's embeddable text
 * changed and that locale's embedding needs to be regenerated.
 *
 * Distinct from {@see TranslatedModelSaved} (outbound auto-translation of
 * missing locales) and {@see ModelRequiresIndexing} (whole-model indexing,
 * no locale scoping): this event lets a module react to a single-locale
 * content change (e.g. a translation observer) without depending on
 * `Modules\AI` directly. AI listens for this event and dispatches its own
 * embedding job — the module boundary stays event-driven in both directions.
 */
final class TranslationRequiresReembedding
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $model,
        public readonly string $locale,
    ) {}
}
