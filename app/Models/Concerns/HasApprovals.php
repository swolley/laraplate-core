<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Approval\Models\Modification as ApprovalModification;
use Approval\Traits\RequiresApproval;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Support\PermissionName;
use TypeError;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 * @phpstan-require-implements \Modules\Core\Contracts\IModeratableModel
 *
 * @phpstan-type HasApprovalsType HasApprovals
 */
trait HasApprovals
{
    use RequiresApproval;

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
     * Whether AI moderation is enabled for this model.
     * Reads optional {@see $ai_moderation_enabled} or settings in group {@code moderation}
     * with name {@code ai_moderation_{table}}. When no setting exists, AI moderation stays disabled.
     */
    public function aiModerationEnabledBySettings(): bool
    {
        if (property_exists($this, 'ai_moderation_enabled')) {
            return (bool) $this->ai_moderation_enabled;
        }

        return app(PerModelSettingResolver::class)->boolean(
            PerModelSettingResolver::nameFor('ai_moderation', $this->getTable()),
            default: false,
        );
    }

    /**
     * Capture a pending modification, then apply the writer's approve-permission credit when N > 1.
     *
     * @param  Model&self  $item
     */
    public static function captureSave($item): bool
    {
        $diff = collect($item->getDirty())
            ->transform(static function ($change, $key) use ($item): array {
                return [
                    'original' => $item->getOriginal($key),
                    'modified' => $item->$key,
                ];
            })->all();

        $has_modification_pending = $item->modifications()
            ->activeOnly()
            ->where('md5', md5(json_encode($diff)))
            ->first();

        $modifier = $item->modifier();

        /** @var class-string<Modification|ApprovalModification> $modification_model */
        $modification_model = config('approval.models.modification', Modification::class);

        $modification = $has_modification_pending ?? new $modification_model();
        $modification->active = true;
        $modification->modifications = $diff;
        $modification->approvers_required = $item->approversRequired;
        $modification->disapprovers_required = $item->disapproversRequired;
        $modification->md5 = md5(json_encode($diff));

        if ($modifier && ($modifier_class = $modifier::class)) {
            $modifier_instance = new $modifier_class();

            $modification->modifier_id = $modifier->{$modifier_instance->getKeyName()};
            $modification->modifier_type = $modifier_class;
        }

        if (is_null($item->{$item->getKeyName()})) {
            $modification->is_update = false;
        }

        if ($has_modification_pending) {
            $modification->save();
        } else {
            $item->modifications()->save($modification);
        }

        $item->applyAuthorApproveCredit($modification);

        return false;
    }

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

        if ($user instanceof User && $this->writerHasApproveCredit($user) && $this->approversRequired <= 1) {
            return false;
        }

        return true;
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
    protected function applyAuthorApproveCredit(Modification|ApprovalModification $modification): void
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
}
