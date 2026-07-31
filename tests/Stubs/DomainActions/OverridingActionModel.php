<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DomainActions;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\OverridesGenericCrudActions;
use Override;

/**
 * Declares an override of the generic `approve` verb without taking the trait
 * that gives it its generic meaning — the legal combination.
 */
final class OverridingActionModel extends Model implements OverridesGenericCrudActions
{
    protected $table = 'overriding_action_models';

    /**
     * @return list<string>
     */
    #[Override]
    public static function overriddenCrudActions(): array
    {
        return ['approve'];
    }
}
