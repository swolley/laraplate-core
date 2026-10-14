<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Concerns\HasDynamicContents;
use Modules\Core\Models\Entity;
use Modules\Core\Tests\Stubs\DynamicEntities\StubEntityType;

/**
 * A model with dynamic contents and no translations: its dynamic fields live in the single `components`
 * column of its own table.
 */
class DynamicOnlyStubModel extends Model
{
    use HasDynamicContents;

    public $timestamps = false;

    protected $table = 'dynamic_only_stub';

    protected $fillable = ['name'];

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
