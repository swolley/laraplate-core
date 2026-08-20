<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\ActionEnum;
use Override;

/**
 * Deletes a single media item from the owner record. Gated by the owner entity's
 * `update` permission.
 */
final class MediaDeleteRequest extends MediaRequest
{
    #[Override]
    public function mediaOperation(): string
    {
        return ActionEnum::Update->value;
    }
}
