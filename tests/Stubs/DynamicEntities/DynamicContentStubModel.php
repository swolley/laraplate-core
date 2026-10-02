<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DynamicEntities;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Concerns\HasDynamicContents;
use Modules\Core\Models\Entity;

/**
 * A model carrying Core's HasDynamicContents, for checks that only look at the model class
 * (which traits it uses, which columns it owns). It has no table and is never persisted.
 */
final class DynamicContentStubModel extends Model
{
    use HasDynamicContents;

    public static function getEntityType(): IDynamicEntityTypable
    {
        return StubEntityType::Pages;
    }

    /**
     * @return class-string<Entity>
     */
    public static function getEntityModelClass(): string
    {
        return Entity::class;
    }
}
