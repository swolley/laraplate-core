<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DomainActions;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with no Core cross-cutting traits, used to exercise registration of a
 * domain verb that collides with nothing.
 */
final class PlainActionModel extends Model
{
    protected $table = 'plain_action_models';
}
