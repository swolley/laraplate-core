<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use TypeError;

/**
 * Captures a write that its author may not apply alone, as a Modification waiting for approval.
 *
 * Derived from cloudcake/laravel-approval (MIT), see LICENSES/laravel-approval.md: the capture
 * body, the modifications relation and applyModificationChanges() were the package's.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @phpstan-type HasApprovalsType HasApprovals
 */
trait HasApprovals
{
    /**
     * How many approvals mark the captured write as accepted.
     */
    protected int $approversRequired = 1;

    /**
     * How many disapprovals mark it as rejected.
     */
    protected int $disapproversRequired = 1;

    /**
     * Whether an approved diff is written to the model.
     */
    protected bool $updateWhenApproved = true;

    /**
     * Whether a decided modification is dropped rather than deactivated. Both keep the
     * package's declared defaults; {@see initializeHasApprovals()} raises
     * $deleteWhenDisapproved for every model and Content lowers both. Task 6 of the
     * approvals plan removes the pair, so a decided modification is always kept.
     */
    protected bool $deleteWhenApproved = true;

    protected bool $deleteWhenDisapproved = false;

    /**
     * Set while an approved modification is being written, so the listener lets it through.
     */
    private bool $forcedApprovalUpdate = false;

    /**
     * Intercept every save and capture it when the writer may not apply it alone.
     *
     * Derived from cloudcake/laravel-approval (MIT), see LICENSES/laravel-approval.md.
     */
    public static function bootHasApprovals(): void
    {
        static::saving(static function (Model $item): ?bool {
            if (! $item->isForcedApprovalUpdate() && $item->requiresApprovalWhen($item->getDirtyForApproval()) === true) {
                return static::captureSave($item);
            }

            $item->setForcedApprovalUpdate(false);

            return null;
        });
    }

    /**
     * Capture a pending modification, then apply the writer's approve-permission credit when N > 1.
     *
     * @param  Model&self  $item
     */
    public static function captureSave($item): bool
    {
        $diff = collect($item->getDirtyForApproval())
            ->transform(static function ($change, $key) use ($item): array {
                return [
                    'original' => $item->getOriginal($key),
                    'modified' => $item->{$key},
                ];
            })->all();

        $has_modification_pending = $item->modifications()
            ->activeOnly()
            ->where('md5', md5(json_encode($diff, JSON_THROW_ON_ERROR)))
            ->first();

        $modifier = $item->modifier();

        $modification = $has_modification_pending ?? new Modification();
        $modification->active = true;
        $modification->modifications = $diff;
        $modification->approvers_required = $item->approversRequired;
        $modification->disapprovers_required = $item->disapproversRequired;
        $modification->md5 = md5(json_encode($diff, JSON_THROW_ON_ERROR));

        if ($modifier && ($modifier_class = $modifier::class)) {
            $modifier_instance = new $modifier_class();

            $modification->modifier_id = $modifier->{$modifier_instance->getKeyName()};
            $modification->modifier_type = $modifier_class;
        }

        $modification->operation = $item->{$item->getKeyName()} === null ? Operation::Create : Operation::Update;

        if ($has_modification_pending) {
            $modification->save();
        } else {
            $item->modifications()->save($modification);
        }

        $item->applyAuthorApproveCredit($modification);

        return false;
    }

    public function isForcedApprovalUpdate(): bool
    {
        return $this->forcedApprovalUpdate;
    }

    public function setForcedApprovalUpdate(bool $forced = true): void
    {
        $this->forcedApprovalUpdate = $forced;
    }

    /**
     * Whoever is writing, recorded on the modification as its author.
     */
    public function modifier(): ?Model
    {
        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * Declared again over RequiresApproval, which returns a bare MorphMany against a
     * configurable class: nothing downstream knew what was on the other end, and the
     * scopes the Modification model carries (activeOnly, inactiveOnly) resolved against
     * Model instead. The class is no longer configurable because the model is ours.
     *
     * @return MorphMany<Modification, $this>
     */
    public function modifications(): MorphMany
    {
        return $this->morphMany(Modification::class, 'modifiable');
    }

    /**
     * Apply a decided modification to the model, or record the decision when nothing is
     * applied. The body is the package's; only the modification type changed.
     *
     * Derived from cloudcake/laravel-approval (MIT), see LICENSES/laravel-approval.md.
     */
    public function applyModificationChanges(Modification $modification, bool $approved): void
    {
        if ($approved && $this->updateWhenApproved) {
            $this->setForcedApprovalUpdate(true);

            foreach ($modification->modifications as $key => $change) {
                $this->{$key} = $change['modified'];
            }

            $this->save();

            $this->settleModification($modification, $this->deleteWhenApproved);

            return;
        }

        if ($approved === false) {
            $this->settleModification($modification, $this->deleteWhenDisapproved);
        }
    }

    public function initializeHasApprovals(): void
    {
        if (preview()) {
            $this->append('preview');
            $this->makeHidden('preview');
        }

        $this->deleteWhenDisapproved = true;
    }

    public function toArray(?array $parsed = null): array
    {
        if (! $this->preview) {
            return parent::toArray();
        }

        $preview = array_merge($this->preview, $this->relationsToArray());
        $preview['_'] = array_merge($this->attributesToArray());

        foreach ($preview['_'] as $key => $value) {
            if ($preview[$key] === $value) {
                unset($preview['_'][$key]);
            }
        }

        return $preview;
    }

    /**
     * The change set a capture decides on. `getDirty()` for most models; a model whose write
     * goes through a staging area (translations, pending scores) folds it in here, as
     * {@see \Modules\CMS\Models\Comment::getDirtyForApproval()} does.
     *
     * @return array<string, mixed>
     */
    protected function getDirtyForApproval(): array
    {
        return $this->getDirty();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getPreviewAttribute(): ?array
    {
        // preview(), not session('preview'): on app/api the flag is request-scoped and
        // the session is deliberately unread.
        if (! preview()) {
            return null;
        }

        $preview = $this->attributesToArray();

        /** @var Modification $modification */
        foreach ($this->modifications()->activeOnly()->oldest()->select(['modifications'])->cursor() as $modification) {
            /** @phpstan-ignore property.notFound */
            foreach ($modification->modifications as $key => $mod) {
                $preview[$key] = $mod['modified'];
            }
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $modifications
     *
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     * @throws TypeError
     */
    protected function requiresApprovalWhen($modifications): bool
    {
        // TODO: need to verify if console operations must be approved or not
        if (App::runningInConsole()) {
            return false;
        }

        if ($modifications === []) {
            return false;
        }

        /** @var User|null $user */
        $user = Auth::user();

        // Whatever a superadmin writes is approved by definition.
        if ($user instanceof User && $user->isSuperAdmin()) {
            return false;
        }

        return ! ($user instanceof User && $this->writerHasApproveCredit($user) && $this->approversRequired <= 1);
    }

    /**
     * One write-time approve credit from `approve.{connection}.{table}`.
     */
    protected function writerHasApproveCredit(User $user): bool
    {
        return $user->can(PermissionName::forModel($this, 'approve'));
    }

    /**
     * When N > 1 and the writer holds approve permission, record one automatic Approval
     * (meta source: author_approve_permission). Does not apply when N = 1 (no mod created).
     */
    protected function applyAuthorApproveCredit(Modification $modification): void
    {
        if ((int) $modification->approvers_required <= 1) {
            return;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user instanceof User || ! $this->writerHasApproveCredit($user)) {
            return;
        }

        $connection = $modification->getConnectionName() ?? $this->getConnectionName();

        (new Approval())->setConnection($connection)->newQuery()->updateOrCreate([
            'approver_id' => $user->getKey(),
            'approver_type' => $user::class,
            'modification_id' => $modification->getKey(),
        ], [
            'reason' => null,
            'meta' => ['source' => 'author_approve_permission'],
        ]);

        $modification->refresh();

        if ((int) $modification->approversRemaining === 0) {
            $this->applyModificationChanges($modification, true);
        }
    }

    /**
     * A decided modification is either dropped or deactivated, never left active.
     */
    private function settleModification(Modification $modification, bool $delete): void
    {
        if ($delete) {
            $modification->delete();

            return;
        }

        $modification->active = false;
        $modification->save();
    }
}
