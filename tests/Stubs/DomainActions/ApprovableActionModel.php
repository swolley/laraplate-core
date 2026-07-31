<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DomainActions;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\OverridesGenericCrudActions;
use Modules\Core\Models\Concerns\HasApprovals;
use Override;

/**
 * Declares an override of `approve` *and* uses HasApprovals — the contradiction
 * the registry must reject, because `approve` would mean two things at once:
 * voting on a pending Modification, and whatever the module defines.
 */
final class ApprovableActionModel extends Model implements OverridesGenericCrudActions
{
    use HasApprovals;

    protected $table = 'approvable_action_models';

    /**
     * @return list<string>
     */
    #[Override]
    public static function overriddenCrudActions(): array
    {
        return ['approve'];
    }
}
