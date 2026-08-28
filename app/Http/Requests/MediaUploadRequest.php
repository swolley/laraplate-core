<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Casts\ActionEnum;
use Override;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * Uploads a file into one of the owner model's registered media collections.
 *
 * A single endpoint with an optional `{id}`:
 * - with an id, the file binds to that record and the request is gated by the
 *   owner entity's `update` permission (plus the target row's ACL);
 * - without an id, the file is staged in the caller's pending bucket and the
 *   request is gated by the `insert` permission — an optional `token` targets an
 *   existing draft, otherwise the server opens a new one and returns its token.
 *
 * The target collection is validated against the model's registered collections;
 * an unknown name is rejected with a 422.
 */
final class MediaUploadRequest extends MediaRequest
{
    #[Override]
    public function mediaOperation(): string
    {
        return $this->route('id') !== null
            ? ActionEnum::Update->value
            : ActionEnum::Insert->value;
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
            'token' => ['sometimes', 'uuid'],
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
        // Not mediaModel(): that throws when no owner is resolved, and the swagger
        // generator reads rules() from a bare instance with no route bound. During a
        // real request prepareForValidation() has already resolved the owner.
        $model = $this->model;

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
