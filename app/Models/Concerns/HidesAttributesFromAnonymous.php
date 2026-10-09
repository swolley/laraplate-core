<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\IHidesAttributesFromAnonymous;
use Modules\Core\Models\User;

/**
 * Removes the attributes a model declares through {@see IHidesAttributesFromAnonymous} from what it serializes
 * for the anonymous user.
 *
 * The user is the authenticated one of the current guard: with nobody acting (console, queue, search indexing)
 * the record is serialized in full. The attributes are filtered out of the serialization itself and never added
 * to the model's own hidden list, so an instance that was serialized for the anonymous user and is serialized
 * again for a staff user, in the same process, still carries them.
 *
 * @phpstan-require-implements IHidesAttributesFromAnonymous
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HidesAttributesFromAnonymous
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function getArrayableItems(array $values): array
    {
        $items = parent::getArrayableItems($values);

        if (! $this->isSerializedForAnonymous()) {
            return $items;
        }

        return array_diff_key($items, array_flip($this->anonymousHiddenAttributes()));
    }

    protected function isSerializedForAnonymous(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isGuest();
    }
}
