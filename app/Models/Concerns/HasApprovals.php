<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use App\Models\User;
use Approval\Models\Modification;
use Approval\Traits\RequiresApproval;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Modules\Core\Services\PerModelSettingResolver;
use TypeError;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
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
            'ai_moderation_' . $this->getTable(),
            default: false,
        );
    }

    protected function getPreviewAttribute(): ?array
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

        /** @var User|null $user */
        $user = Auth::user();

        /** @phpstan-ignore method.notFound */
        if ($user && ($user->isAdmin() || $user->isSuperAdmin() && $user->can('approve.' . $this->getTable()))) {
            return false;
        }

        return $modifications !== [];
    }
}
