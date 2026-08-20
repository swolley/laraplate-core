<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\ActionEnum;
use Override;

/**
 * Lists the owner record's media. Gated by the owner entity's `select` permission.
 */
final class MediaListRequest extends MediaRequest
{
    #[Override]
    public function mediaOperation(): string
    {
        return ActionEnum::Select->value;
    }
}
