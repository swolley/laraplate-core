<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Core\Models\ImportSession;

/**
 * The in-app notification raised when a bulk import finishes. It writes only the
 * database channel: a structured payload the SPA tray and Filament's native bell
 * both read. The payload is UI-locale-agnostic — a machine `type` plus a `meta`
 * bag the SPA localizes itself — with English `title`/`body` as a backoffice
 * fallback. `scope` is the module the imported entity belongs to (derived from the
 * `entity_key` prefix), and `action` is the semantic click target each surface
 * resolves to its own route.
 */
final class ImportFinishedNotification extends Notification
{
    public function __construct(
        private readonly ImportSession $session,
        private readonly bool $failed = false,
        private readonly ?string $error = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{
     *     type: string,
     *     level: string,
     *     scope: string,
     *     title: string,
     *     body: string,
     *     action: array{target: string, id: int, view: string},
     *     meta: array{entity_key: string, created: int, updated: int, skipped: int, failed: int}
     * }
     */
    public function toArray(object $notifiable): array
    {
        $session = $this->session;
        $hasRowErrors = (int) $session->failed_rows > 0;
        $level = $this->failed ? 'danger' : ($hasRowErrors ? 'warning' : 'success');

        return [
            'type' => 'import.finished',
            'level' => $level,
            'scope' => $this->module($session->entity_key),
            'title' => $this->failed ? 'Import failed' : 'Import completed',
            'body' => $this->failed
                ? ($this->error ?? 'The import could not be completed.')
                : sprintf(
                    '%d created · %d updated · %d skipped · %d failed',
                    $session->created_rows,
                    $session->updated_rows,
                    $session->skipped_rows,
                    $session->failed_rows,
                ),
            'action' => [
                'target' => 'import_session',
                'id' => (int) $session->id,
                'view' => $hasRowErrors || $this->failed ? 'errors' : 'summary',
            ],
            'meta' => [
                'entity_key' => $session->entity_key,
                'created' => (int) $session->created_rows,
                'updated' => (int) $session->updated_rows,
                'skipped' => (int) $session->skipped_rows,
                'failed' => (int) $session->failed_rows,
            ],
        ];
    }

    /**
     * The owning module slug, taken from the `entity_key` prefix (e.g. `cms.content`
     * → `cms`), defaulting to `core`.
     */
    private function module(string $entityKey): string
    {
        $prefix = explode('.', $entityKey)[0];

        return $prefix !== '' ? $prefix : 'core';
    }
}
