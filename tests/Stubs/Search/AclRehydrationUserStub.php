<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use App\Models\User;
use Modules\Core\Search\Traits\Searchable as CoreSearchable;
use Override;

/**
 * A user that is searchable through an injected engine and has no rehydration guard of its own, like every
 * model but Media: the only thing keeping a search hit from reaching a user who may not see it is the
 * ACL row filters, in the engine query and again when the hits are reloaded.
 */
final class AclRehydrationUserStub extends User
{
    use CoreSearchable;

    public static mixed $engine = null;

    #[Override]
    public function getMorphClass(): string
    {
        return User::class;
    }

    public function searchableUsing(): mixed
    {
        return self::$engine;
    }
}
