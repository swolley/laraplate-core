<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Modules\Core\Casts\RelationFilter;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * The one check a stored relation name goes through before a query calls it.
 *
 * `whereHas('name')` makes Laravel call `$model->name()`, and `Model::__call` forwards an unknown name to a
 * query, so a name such as `truncate` would run. A name is therefore trusted only when it is a public,
 * non-static method with no required parameter whose declared return type is a relation. ACL relation
 * filters use it on every query and at save; any other caller that turns a name from storage or from a
 * request into a relation call can use the same two methods.
 */
final class RelationGuard
{
    /**
     * The relation behind `$name`, or null when `$name` is not a safe relation method of the model. The method
     * is called only after its declaration has been checked.
     *
     * @return Relation<Model, Model, mixed>|null
     */
    public static function relationOf(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }

        try {
            $method = new ReflectionMethod($model, $name);
        } catch (ReflectionException) {
            return null;
        }

        $type = $method->getReturnType();

        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        if (! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Relation::class, true)) {
            return null;
        }

        try {
            $relation = $model->{$name}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof Relation ? $relation : null;
    }

    /**
     * The model class a morph type stands for (a class name or a morph alias), or null when it is the
     * wildcard or does not name a model.
     *
     * @return class-string<Model>|null
     */
    public static function morphModelClass(string $type): ?string
    {
        if ($type === '' || $type === '*') {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * Checks a relation filter against the model it is applied to: the name is a safe
     * relation method, `morph_types` is given exactly when the relation is a MorphTo, and each morph type is a
     * model (never the wildcard, which Laravel would expand to every type).
     *
     * @throws InvalidArgumentException when the filter cannot be applied safely, so the query fails closed
     */
    public static function assertFilterable(Model $model, RelationFilter $filter): void
    {
        $relation = self::relationOf($model, $filter->relation);

        if (! $relation instanceof Relation) {
            throw new InvalidArgumentException(sprintf('"%s" is not a relation of %s.', $filter->relation, $model::class));
        }

        if (! $relation instanceof MorphTo) {
            if ($filter->morph_types !== null) {
                throw new InvalidArgumentException(sprintf('Relation "%s" of %s is not a morph relation, morph types must not be given.', $filter->relation, $model::class));
            }

            return;
        }

        if ($filter->morph_types === null || $filter->morph_types === []) {
            throw new InvalidArgumentException(sprintf('Morph relation "%s" of %s needs its morph types.', $filter->relation, $model::class));
        }

        foreach ($filter->morph_types as $type) {
            if (self::morphModelClass($type) === null) {
                throw new InvalidArgumentException(sprintf('Morph type "%s" of relation "%s" is not a model.', $type, $filter->relation));
            }
        }
    }
}
