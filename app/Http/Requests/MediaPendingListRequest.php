<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\ActionEnum;
use Override;

/**
 * Lists the current user's pending media for a token before the owner record
 * exists. Gated by the owner entity's `insert` permission.
 */
final class MediaPendingListRequest extends MediaRequest
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
