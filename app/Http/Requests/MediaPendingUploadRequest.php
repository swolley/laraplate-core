<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Casts\ActionEnum;
use Override;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * Uploads a file into the token-keyed pending bucket for a CREATE form, before
 * the owner record exists. Gated by the owner entity's `insert` permission. The
 * intended `collection` is validated against the target model's registered
 * collections; an unknown name is rejected with a 422.
 */
final class MediaPendingUploadRequest extends MediaRequest
{
    #[Override]
    public function mediaOperation(): string
    {
        return ActionEnum::Insert->value;
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
            'token' => ['required', 'uuid'],
            'name' => ['sometimes', 'string'],
            'custom_properties' => ['sometimes', 'array'],
        ]);
    }

    /**
     * The names of the media collections registered on the target owner model.
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
