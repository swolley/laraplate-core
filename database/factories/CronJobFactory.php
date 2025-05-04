<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Override;
use Modules\Core\Models\CronJob;
use Illuminate\Database\Eloquent\Factories\Factory;

final class CronJobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = CronJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'name' => 'inspire',
            'command' => 'php artisan inspire',
            // 'parameters',
            'schedule' => '9 * * * * *',
            'description' => 'Display an inspiring quote',
            'is_active' => false,
        ];
    }
}
