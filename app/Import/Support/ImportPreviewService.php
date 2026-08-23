<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\ValueObjects\ImportField;
use Modules\Core\Import\ValueObjects\ImportPreview;
use Modules\Core\Models\ImportSession;

/**
 * Builds the interactive mapping preview for a session: reads the source columns
 * and the first rows, pairs them with the target entity's fields, and proposes a
 * mapping by matching normalized header names. Also the one place that resolves and
 * persists a session's detected columns on upload.
 */
final readonly class ImportPreviewService
{
    public function __construct(
        private SourceReaderFactory $readers,
        private EntityImporterRegistry $registry,
    ) {}

    /**
     * Read the source headers, store them on the session, and return them.
     *
     * @return list<string>
     */
    public function detectColumns(ImportSession $session): array
    {
        $columns = $this->readers->for($session->source_format)
            ->headers($this->path($session), $session->options ?? []);

        $session->forceFill(['detected_columns' => $columns])->save();

        return $columns;
    }

    public function preview(ImportSession $session, int $limit = 20): ImportPreview
    {
        $importer = $this->registry->get($session->entity_key);
        $reader = $this->readers->for($session->source_format);
        $path = $this->path($session);
        $options = $session->options ?? [];

        $columns = $session->detected_columns ?? $reader->headers($path, $options);

        $rows = [];

        foreach ($reader->rows($path, $options) as $row) {
            if ($limit <= count($rows)) {
                break;
            }

            $rows[] = $row;
        }

        return new ImportPreview(
            columns: array_values($columns),
            rows: $rows,
            fields: $importer->fields(),
            suggestedMapping: $this->autoMatch($importer->fields(), $columns),
        );
    }

    /**
     * Match each field to the first column whose normalized header equals the
     * field's normalized name, label or one of its aliases.
     *
     * @param  list<ImportField>  $fields
     * @param  list<string>  $columns
     * @return array<string, string|null>
     */
    private function autoMatch(array $fields, array $columns): array
    {
        $normalizedColumns = [];

        foreach ($columns as $column) {
            $normalizedColumns[$this->normalize($column)] ??= $column;
        }

        $mapping = [];

        foreach ($fields as $field) {
            $candidates = array_map($this->normalize(...), [$field->name, $field->label, ...$field->aliases]);
            $mapping[$field->name] = null;

            foreach ($candidates as $candidate) {
                if (isset($normalizedColumns[$candidate])) {
                    $mapping[$field->name] = $normalizedColumns[$candidate];

                    break;
                }
            }
        }

        return $mapping;
    }

    private function normalize(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
    }

    private function path(ImportSession $session): string
    {
        return Storage::disk($session->file_disk)->path($session->file_path);
    }
}
