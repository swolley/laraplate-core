<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Search\Contracts\IAuthorizesSearchRehydration;
use Modules\Core\Search\Traits\Searchable as CoreSearchable;
use Override;

/**
 * A searchable user whose rehydration hides one key, to prove CrudService applies
 * {@see IAuthorizesSearchRehydration} when it turns search hits back into models.
 */
final class RehydrationGuardedUserStub extends User implements IAuthorizesSearchRehydration
{
    use CoreSearchable;

    public static mixed $engine = null;

    public static mixed $hiddenKey = null;

    #[Override]
    public function getMorphClass(): string
    {
        return User::class;
    }

    public function searchableUsing(): mixed
    {
        return self::$engine;
    }

    #[Override]
    public function authorizeSearchRehydration(Builder $query): Builder
    {
        return $query->whereKeyNot(self::$hiddenKey);
    }
}
