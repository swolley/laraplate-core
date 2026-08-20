<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\MediaDraft;
use Modules\Core\Models\User;
use Override;

/**
 * @extends Factory<MediaDraft>
 */
final class MediaDraftFactory extends Factory
{
    /**
     * @var class-string<MediaDraft>
     */
    protected $model = MediaDraft::class;

    #[Override]
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => fake()->uuid(),
            'target_module' => 'cms',
            'target_entity' => 'contents',
        ];
    }
}
