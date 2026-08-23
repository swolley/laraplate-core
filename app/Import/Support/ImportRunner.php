<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Contracts\EntityImporterInterface;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Import\Events\ImportSessionCompleted;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Models\ImportSession;
use Throwable;

/**
 * The engine behind an import: it streams the source, maps each row through the
 * session's column mapping, and upserts it via the registered entity importer.
 *
 * Rows are committed in chunks (per-chunk commit, so a large file makes durable
 * progress and its counters advance as it goes), and each row runs in its own
 * savepoint — a row that raises {@see RowImportException} (or any error) rolls back
 * only itself, is recorded in the failure report, and never aborts the chunk or the
 * import. On completion it fires {@see ImportSessionCompleted}; a fatal error before
 * the stream finishes propagates so the job can mark the session failed.
 */
final readonly class ImportRunner
{
    private const int CHUNK_SIZE = 200;

    public function __construct(
        private SourceReaderFactory $readers,
        private EntityImporterRegistry $registry,
        private DatabaseManager $database,
    ) {}

    public function process(ImportSession $session): void
    {
        $importer = $this->registry->get($session->entity_key);
        $reader = $this->readers->for($session->source_format);
        $connection = $this->database->connection();
        $path = Storage::disk($session->file_disk)->path($session->file_path);

        $session->forceFill([
            'status' => ImportSessionStatus::Processing,
            'started_at' => now(),
            'processed_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
        ])->save();

        $rowNumber = 0;
        $chunk = [];

        foreach ($reader->rows($path, $session->options ?? []) as $sourceRow) {
            $rowNumber++;
            $chunk[] = [$rowNumber, $this->mapRow($session->mapping ?? [], $sourceRow)];

            if (count($chunk) >= self::CHUNK_SIZE) {
                $this->processChunk($session, $importer, $connection, $chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->processChunk($session, $importer, $connection, $chunk);
        }

        $session->forceFill(['status' => ImportSessionStatus::Completed, 'finished_at' => now()])->save();

        ImportSessionCompleted::dispatch($session);
    }

    /**
     * @param  list<array{0: int, 1: array<string, string>}>  $chunk
     */
    private function processChunk(ImportSession $session, EntityImporterInterface $importer, ConnectionInterface $connection, array $chunk): void
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        /** @var list<array{import_session_id: int, row_number: int, errors: array<string, list<string>>, raw: array<string, string>}> $errors */
        $errors = [];

        $connection->transaction(function () use ($chunk, $importer, $session, $connection, &$counts, &$errors): void {
            foreach ($chunk as [$rowNumber, $mapped]) {
                try {
                    $outcome = $connection->transaction(
                        static fn () => $importer->import($mapped, new ImportRowContext($session, $rowNumber)),
                    );
                    $counts[$outcome->value]++;
                } catch (RowImportException $exception) {
                    $errors[] = $this->errorRow($session, $rowNumber, $exception->errors(), $mapped);
                } catch (Throwable $exception) {
                    $errors[] = $this->errorRow($session, $rowNumber, ['_' => [$exception->getMessage()]], $mapped);
                }
            }
        });

        if ($errors !== []) {
            $session->rowErrors()->createMany($errors);
        }

        $session->forceFill([
            'processed_rows' => $session->processed_rows + count($chunk),
            'created_rows' => $session->created_rows + $counts['created'],
            'updated_rows' => $session->updated_rows + $counts['updated'],
            'skipped_rows' => $session->skipped_rows + $counts['skipped'],
            'failed_rows' => $session->failed_rows + count($errors),
        ])->save();
    }

    /**
     * @param  array<string, list<string>>  $messages
     * @param  array<string, string>  $mapped
     * @return array{import_session_id: int, row_number: int, errors: array<string, list<string>>, raw: array<string, string>}
     */
    private function errorRow(ImportSession $session, int $rowNumber, array $messages, array $mapped): array
    {
        return [
            'import_session_id' => $session->getKey(),
            'row_number' => $rowNumber,
            'errors' => $messages,
            'raw' => $mapped,
        ];
    }

    /**
     * Reduce one raw source row to the mapped target fields. An unmapped field
     * (null/empty column) is dropped; the importer decides whether a missing
     * required field is a row error.
     *
     * @param  array<string, string>  $mapping  field name => source column header
     * @param  array<string, string>  $sourceRow
     * @return array<string, string>
     */
    private function mapRow(array $mapping, array $sourceRow): array
    {
        $mapped = [];

        foreach ($mapping as $field => $column) {
            if (! is_string($column) || $column === '') {
                continue;
            }

            $mapped[$field] = $sourceRow[$column] ?? '';
        }

        return $mapped;
    }
}
