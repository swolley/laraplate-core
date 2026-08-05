<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Override;

/**
 * Resolves module/entity and requires the target record id for rejection feedback.
 */
final class LatestDisapprovalRequest extends CrudRequest
{
    #[Override]
    public function rules(): array
    {
        return parent::rules() + [
            'id' => ['required'],
        ];
    }
}
