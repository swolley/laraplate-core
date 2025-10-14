<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use InvalidArgumentException;
use Override;

class ComposhipsModel extends Model
{
    use HasFactory;

    #[Override]
    public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        // Se non è un array di chiavi, usa il comportamento standard
        if (! is_array($foreignPivotKey) && ! is_array($relatedPivotKey)) {
            return parent::belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation);
        }

        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }

        // Gestione delle chiavi composite
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        return $this->newBelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation,
        );
    }

    #[Override]
    protected function newBelongsToMany(
        EloquentBuilder $query,
        BaseModel $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ) {
        return new ComposhipsBelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
        );
    }

    /**
     * Validate the related model for Compoships compatibility.
     */
    protected function validateRelatedModel($related): void
    {
        throw_unless(is_subclass_of($related, self::class), InvalidArgumentException::class, "The related model '{$related}' must extend " . self::class);
    }
}
