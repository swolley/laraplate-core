<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Approval\Models\Modification;
use Approval\Traits\RequiresApproval;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use Modules\Core\Models\User;
use TypeError;

/**
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

    public function getPreviewAttribute(): ?array
    {
        if (! session('preview', false)) {
            return null;
        }

        $preview = $this->attributesToArray();

        /** @var Modification $modification */
        foreach ($this->modifications()->oldest()->select(['modifications'])->cursor() as $modification) {
            /** @phpstan-ignore property.notFound */
            foreach ($modification->modifications as $key => $mod) {
                $preview[$key] = $mod['modified'];
            }
        }

        return $preview;
    }

    public function toArray()
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
     * @param  array<string, mixed>  $modifications
     *
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     * @throws TypeError
     */
    protected function requiresApprovalWhen($modifications): bool
    {
        /** @var null|User $user */
        $user = auth()?->user();

        /** @phpstan-ignore method.notFound */
        if ($user && ($user->isAdmin() || $user->isSuperAdmin() && $user->can('approve.' . $this->getTable()))) {
            return false;
        }

        return $modifications !== [];
    }
}
