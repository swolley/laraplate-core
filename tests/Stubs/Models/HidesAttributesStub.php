<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IHidesAttributesFromAnonymous;
use Modules\Core\Models\Concerns\HidesAttributesFromAnonymous;
use Override;

/**
 * A model that declares one personal attribute, `user_id`, never returned to the anonymous user.
 */
final class HidesAttributesStub extends Model implements IHidesAttributesFromAnonymous
{
    use HidesAttributesFromAnonymous;

    protected $guarded = [];

    #[Override]
    public function anonymousHiddenAttributes(): array
    {
        return ['user_id'];
    }
}
