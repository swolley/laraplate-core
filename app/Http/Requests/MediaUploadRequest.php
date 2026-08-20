<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Casts\ActionEnum;
use Override;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * Uploads a file into one of the owner model's registered media collections.
 * Gated by the owner entity's `update` permission. The target collection is
 * validated against the model's registered collections; an unknown name is
 * rejected with a 422.
 */
final class MediaUploadRequest extends MediaRequest
{
    #[Override]
    public function mediaOperation(): string
    {
        return ActionEnum::Update->value;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    #[Override]
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'file' => ['required', 'file'],
            'collection' => ['required', 'string', Rule::in($this->registeredCollections())],
            'name' => ['sometimes', 'string'],
            'custom_properties' => ['sometimes', 'array'],
        ]);
    }

    /**
     * The names of the media collections registered on the owner model.
     *
     * @return list<string>
     */
    private function registeredCollections(): array
    {
        $model = $this->mediaModel();

        if (! $model instanceof SpatieHasMedia) {
            return [];
        }

        /** @phpstan-ignore method.notFound */
        return collect($model->getRegisteredMediaCollections())
            ->pluck('name')
            ->filter(static fn (mixed $name): bool => is_string($name))
            ->values()
            ->all();
    }
}
