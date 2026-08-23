<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ImportRowError;
use Modules\Core\Models\ImportSession;
use Override;

/**
 * @extends Factory<ImportRowError>
 */
final class ImportRowErrorFactory extends Factory
{
    /**
     * @var class-string<ImportRowError>
     */
    protected $model = ImportRowError::class;

    #[Override]
    public function definition(): array
    {
        return [
            'import_session_id' => ImportSession::factory(),
            'row_number' => fake()->numberBetween(1, 1000),
            'errors' => ['_' => [fake()->sentence()]],
            'raw' => ['column' => fake()->word()],
        ];
    }
}
