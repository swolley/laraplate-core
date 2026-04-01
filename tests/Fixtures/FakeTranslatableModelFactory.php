<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Overrides\Factory;

/**
 * @extends Factory<FakeTranslatableModel>
 */
final class FakeTranslatableModelFactory extends Factory
{
    protected $model = FakeTranslatableModel::class;

    protected function definitionsArray(): array
    {
        return [];
    }

    protected function beforeFactoryCreating(Model $model): void
    {
        // Demonstrate that child factories can customize the pipeline.
        // This value is used by translatedFieldsArray() below.
        $model->title = 'Overridden Title';
    }

    protected function translatedFieldsArray(Model $model): array
    {
        return [
            'title' => $model->title ?? 'Default Title',
            'slug' => 'default-slug',
            'components' => [
                'body' => [
                    'blocks' => [
                        ['type' => 'paragraph', 'data' => ['text' => 'Hello']],
                    ],
                ],
            ],
        ];
    }
}

