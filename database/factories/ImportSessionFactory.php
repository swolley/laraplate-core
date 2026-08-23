<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Models\ImportSession;
use Override;

/**
 * @extends Factory<ImportSession>
 */
final class ImportSessionFactory extends Factory
{
    /**
     * @var class-string<ImportSession>
     */
    protected $model = ImportSession::class;

    #[Override]
    public function definition(): array
    {
        return [
            'user_id' => null,
            'entity_key' => 'core.user',
            'source_format' => ImportSourceFormat::Csv,
            'file_disk' => 'local',
            'file_path' => 'imports/' . fake()->uuid() . '.csv',
            'original_filename' => fake()->word() . '.csv',
            'status' => ImportSessionStatus::Draft,
            'detected_columns' => null,
            'mapping' => null,
            'options' => null,
            'total_rows' => null,
            'processed_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function mapped(string $entityKey, array $mapping): self
    {
        return $this->state(fn (): array => [
            'entity_key' => $entityKey,
            'mapping' => $mapping,
            'detected_columns' => array_values($mapping),
        ]);
    }
}
