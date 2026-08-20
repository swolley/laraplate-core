<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\ActionEnum;
use Override;

/**
 * Moves the current user's pending media for a token onto the freshly created
 * owner record. Gated by the owner entity's `update` permission and, in the
 * controller, by the row-level ACL on the target record.
 */
final class MediaClaimRequest extends MediaRequest
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
            'token' => ['required', 'uuid'],
        ]);
    }
}
