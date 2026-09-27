<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

/**
 * Casts an approval or disapproval vote on a pending modification and applies it once the quorum is reached.
 *
 * Shared by the CRUD API and the Filament panel so both vote through the same rules.
 */
final class ModificationVoteService
{
    /**
     * Whether the user may cast this vote: the author of a modification never votes on it.
     */
    public function canVote(User $user, Modification $modification, bool $approval): bool
    {
        return (bool) $modification->active && $user->isAuthorizedToCastApprovalVote($modification, $approval);
    }

    /**
     * Cast a vote using the modification owner's connection.
     *
     * @return bool false when the user is not authorized to vote
     */
    public function cast(User $user, Modification $modification, bool $approval, ?string $reason = null, ?Model $modifiable = null): bool
    {
        $connection = $modification->getConnectionName();

        if ($modifiable instanceof Model) {
            $modification->setRelation('modifiable', $modifiable);
        }

        if (! $user->isAuthorizedToCastApprovalVote($modification, $approval)) {
            return false;
        }

        $vote = $approval
            ? (new Approval)->setConnection($connection)
            : (new Disapproval)->setConnection($connection);
        $opposite_vote = $approval
            ? (new Disapproval)->setConnection($connection)
            : (new Approval)->setConnection($connection);
        $actor_id_column = $approval ? 'approver_id' : 'disapprover_id';
        $actor_type_column = $approval ? 'approver_type' : 'disapprover_type';
        $opposite_id_column = $approval ? 'disapprover_id' : 'approver_id';
        $opposite_type_column = $approval ? 'disapprover_type' : 'approver_type';

        $opposite_vote->newQuery()->where([
            $opposite_id_column => $user->getKey(),
            $opposite_type_column => $user::class,
            'modification_id' => $modification->getKey(),
        ])->delete();

        $vote->newQuery()->updateOrCreate([
            $actor_id_column => $user->getKey(),
            $actor_type_column => $user::class,
            'modification_id' => $modification->getKey(),
        ], [
            'reason' => $reason,
        ]);

        $modification->refresh();
        $remaining = $approval
            ? $modification->approversRemaining
            : $modification->disapproversRemaining;

        if ($remaining !== 0) {
            return true;
        }

        if ($modification->modifiable_id === null) {
            throw_unless(is_string($modification->modifiable_type), LogicException::class, 'Modifiable type is required.');
            $modifiable_type = $modification->modifiable_type;

            /** @var Model $target */
            $target = (new $modifiable_type)->setConnection($connection);
        } else {
            /** @var Model $target */
            $target = $modification->modifiable;
        }

        $target->applyModificationChanges($modification, $approval);

        return true;
    }
}
