<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Jobs\ProcessImportSessionJob;
use Modules\Core\Models\ImportSession;

/**
 * Validates and launches a mapped import. Shared by the SPA API and the Filament
 * surface so the "every required field must be mapped before it can run" rule and
 * the queue-and-mark-queued step live in exactly one place.
 */
final readonly class ImportLauncher
{
    public function __construct(private EntityImporterRegistry $registry) {}

    /**
     * The target entity's required fields that have no mapped source column.
     *
     * @return list<string>
     */
    public function missingRequiredFields(ImportSession $session): array
    {
        if (! $this->registry->has($session->entity_key)) {
            return [];
        }

        $mapping = $session->mapping ?? [];
        $missing = [];

        foreach ($this->registry->get($session->entity_key)->fields() as $field) {
            if ($field->required && ($mapping[$field->name] ?? '') === '') {
                $missing[] = $field->name;
            }
        }

        return $missing;
    }

    /**
     * Whether the session can still be launched (not already running or finished).
     */
    public function isLaunchable(ImportSession $session): bool
    {
        return ! $session->status->isTerminal() && $session->status !== ImportSessionStatus::Processing;
    }

    /**
     * Mark the session queued and dispatch its processing job.
     */
    public function queue(ImportSession $session): void
    {
        $session->forceFill(['status' => ImportSessionStatus::Queued])->save();

        ProcessImportSessionJob::dispatch($session->getKey());
    }
}
