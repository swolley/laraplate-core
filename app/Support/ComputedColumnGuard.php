<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The checks a name from a CRUD read request goes through before it can make a model run code.
 *
 * A `method` column calls the named method on every returned row, an appended attribute runs its accessor, and
 * reading an attribute by name (a group_by key, an in-memory facet) makes Eloquent call a same-named method as if
 * it were a relation. A request may therefore only invoke a method the model declares as computable for the CRUD
 * (a key of its `crudComputedDependencies()` map, the declaration the query builder already reads for the
 * columns and relations a computed column needs), only append an attribute that has an accessor, and never read
 * as an attribute a name that is a method other than a relation or an accessor. Nothing is called to decide.
 */
final class ComputedColumnGuard
{
    /**
     * Methods that write, whatever a model declares: refused even when listed as computable.
     *
     * @var list<string>
     */
    private const array MUTATORS = [
        'attach', 'create', 'decrement', 'delete', 'deleteQuietly', 'destroy', 'detach', 'fill', 'forceCreate',
        'forceDelete', 'forceDeleteQuietly', 'forceFill', 'increment', 'insert', 'push', 'pushQuietly', 'refresh',
        'restore', 'restoreQuietly', 'save', 'saveOrFail', 'saveQuietly', 'sync', 'touch', 'touchQuietly',
        'truncate', 'update', 'updateOrFail', 'updateQuietly', 'upsert',
    ];

    /**
     * @throws InvalidArgumentException when the model does not declare the method as computable or it is not a
     *                                  safe method to call on a read
     */
    public static function assertMethod(Model $model, string $name): void
    {
        if (! self::isDeclaredComputed($model, $name) || ! self::isSafeMethod($model, $name)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a computed method of %s.', $name, $model::class));
        }
    }

    /**
     * @throws InvalidArgumentException when the attribute has no accessor on the model
     */
    public static function assertAppend(Model $model, string $name): void
    {
        if (! self::hasAccessor($model, $name)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an attribute accessor of %s.', $name, $model::class));
        }
    }

    /**
     * A name read as an attribute: a column, an accessor or a relation, never another method, which Eloquent
     * would call as if it were a relation.
     *
     * @throws InvalidArgumentException when the name is a method that is neither an accessor nor a relation
     */
    public static function assertReadableAttribute(Model $model, string $name): void
    {
        if (self::hasAccessor($model, $name) || ! method_exists($model, $name)) {
            return;
        }

        if (RelationGuard::relationOf($model, $name) instanceof Relation) {
            return;
        }

        throw new InvalidArgumentException(sprintf('"%s" is a method of %s, not an attribute.', $name, $model::class));
    }

    /**
     * A dotted attribute path (`relation.relation.attribute`): every relation checked by {@see RelationGuard},
     * the attribute by {@see assertReadableAttribute()}.
     *
     * @throws InvalidArgumentException when a segment is neither a relation nor a readable attribute
     */
    public static function assertReadablePath(Model $model, string $path): void
    {
        $segments = explode('.', $path);
        $attribute = (string) array_pop($segments);

        self::assertReadableAttribute(self::modelAt($model, $segments), $attribute);
    }

    /**
     * The model a relation path leads to, every name checked by {@see RelationGuard}. A morph relation cannot be
     * followed: its related model depends on each row.
     *
     * @param  list<string>  $relations
     *
     * @throws InvalidArgumentException when a name is not a relation or follows a morph relation
     */
    public static function modelAt(Model $model, array $relations): Model
    {
        $current = $model;

        foreach ($relations as $name) {
            $relation = RelationGuard::relationOf($current, $name);

            if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                throw new InvalidArgumentException(sprintf('"%s" is not a relation of %s that a path can follow.', $name, $current::class));
            }

            $current = $relation->getRelated();
        }

        return $current;
    }

    /**
     * Whether a method may be called on a row by the CRUD read: a public, non-static method with no required
     * parameter, written in the application (not inherited from Eloquent, its query builders or a vendor
     * package), not a relation and not a write.
     */
    public static function isSafeMethod(Model $model, string $name): bool
    {
        if (
            in_array($name, self::MUTATORS, true)
            || ! method_exists($model, $name)
            || method_exists(Model::class, $name)
            || method_exists(EloquentBuilder::class, $name)
            || method_exists(QueryBuilder::class, $name)
        ) {
            return false;
        }

        $method = new ReflectionMethod($model, $name);

        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType && is_a($type->getName(), Relation::class, true)) {
            return false;
        }

        $file = $method->getFileName();

        return $file === false || ! str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR);
    }

    private static function isDeclaredComputed(Model $model, string $name): bool
    {
        if (! method_exists($model, 'crudComputedDependencies')) {
            return false;
        }

        $declared = $model->crudComputedDependencies();

        return is_array($declared)
            && (array_key_exists($name, $declared) || array_key_exists($model->getTable() . '.' . $name, $declared));
    }

    private static function hasAccessor(Model $model, string $name): bool
    {
        return $model->hasGetMutator($name) || $model->hasAttributeGetMutator($name);
    }
}
