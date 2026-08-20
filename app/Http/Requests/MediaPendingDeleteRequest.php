<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\ActionEnum;
use Override;

/**
 * Deletes a single pending media item from the current user's bucket for a token.
 * Gated by the owner entity's `insert` permission.
 */
final class MediaPendingDeleteRequest extends MediaRequest
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
            'token' => ['required', 'uuid'],
        ]);
    }
}
