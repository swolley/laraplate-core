<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Approvals\Operation;
use Modules\Core\Events\ModificationApproved;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

/**
 * Casts an approval or disapproval vote on a pending modification and applies it once the quorum is reached.
 *
 * Shared by the CRUD API and the Filament panel so both vote through the same rules.
 *
 * Derived from cloudcake/laravel-approval (MIT), see LICENSES/laravel-approval.md: it took over the
 * voting and application the package's ApprovesChanges trait performed on the user model.
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
     * Cast a vote using the modification owner's connection. The vote and, when it completes
     * the quorum, the application of the decision run in one transaction: if applying fails,
     * the vote is rolled back and the request stays pending.
     *
     * @return bool false when the user is not authorized to vote
     */
    public function cast(User $user, Modification $modification, bool $approval, ?string $reason = null, ?Model $modifiable = null): bool
    {
        return $modification->getConnection()->transaction(fn (): bool => $this->castInTransaction($user, $modification, $approval, $reason, $modifiable));
    }

    /**
     * Apply a modification whose quorum was completed by the author's own approve credit.
     * Not a vote: cast() would refuse it, because the author never votes on their own request.
     */
    public function applyAuthorCredit(Modification $modification, Model $modifiable): void
    {
        $modification->getConnection()->transaction(function () use ($modification, $modifiable): void {
            $modifiable->applyModificationChanges($modification, true);

            if ($modification->operation->isDeletion()) {
                $this->rejectPendingUpdatesOf($modification, $modification->modifier);
            }
        });

        event(new ModificationApproved($modification, $modifiable));
    }

    private function castInTransaction(User $user, Modification $modification, bool $approval, ?string $reason, ?Model $modifiable): bool
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
            /** @var Model $target A restore targets a trashed record, which the relation's scope hides. */
            $target = $modification->operation === Operation::Restore
                ? $modification->modifiable()->withTrashed()->first()
                : $modification->modifiable;
        }

        $target->applyModificationChanges($modification, $approval);

        if ($approval && $modification->operation->isDeletion()) {
            $this->rejectPendingUpdatesOf($modification, $user);
        }

        return true;
    }

    /**
     * An approved deletion leaves nothing for the record's pending updates to change: they are
     * rejected on the deciding user's behalf, with a reason saying why.
     */
    private function rejectPendingUpdatesOf(Modification $deletion, Model $decider): void
    {
        $deletion->newQuery()
            ->where('modifiable_type', $deletion->modifiable_type)
            ->where('modifiable_id', $deletion->modifiable_id)
            ->where('operation', Operation::Update->value)
            ->activeOnly()
            ->each(static function (Modification $update) use ($decider): void {
                $update->disapprovals()->create([
                    'disapprover_id' => $decider->getKey(),
                    'disapprover_type' => $decider::class,
                    'reason' => 'record deleted',
                ]);
                $update->active = false;
                $update->save();
            });
    }
}
