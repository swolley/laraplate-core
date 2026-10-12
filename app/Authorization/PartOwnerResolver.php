<?php

declare(strict_types=1);

namespace Modules\Core\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Contracts\IsPartOfParent;
use Modules\Core\Support\RelationGuard;

/**
 * Resolves the owner of a part of its parent ({@see IsPartOfParent}): the first model up its chain of parent
 * relations that is not itself a part, as {@see \Modules\Core\Services\Crud\RelationAuthorizer} decides the
 * visibility of a part.
 *
 * The answer depends on the part's class alone (its declared parent relation and the related class), never on
 * the record or on the acting user, so it is memoized per class for the life of the process: climbing the chain
 * builds a relation and a parent instance per step, which a list of thousands of lines would otherwise pay per row.
 */
final class PartOwnerResolver
{
    /**
     * How far a chain of parts is climbed to find its owner.
     */
    public const int MAX_DEPTH = 8;

    /**
     * @var array<class-string<Model>, class-string<Model>|null>
     */
    private static array $owners = [];

    /**
     * The class of the first owner of the part that is not a part. Null when the model is not a part, or when its
     * chain does not resolve to a relation to one parent model (an undeclared or morph relation, a cycle, a chain
     * longer than {@see self::MAX_DEPTH}): the caller then keeps the model's own permission in charge.
     *
     * @return class-string<Model>|null
     */
    public static function ownerClassOf(Model $model): ?string
    {
        if (! $model instanceof IsPartOfParent) {
            return null;
        }

        if (array_key_exists($model::class, self::$owners)) {
            return self::$owners[$model::class];
        }

        return self::$owners[$model::class] = self::climb($model);
    }

    /**
     * @return class-string<Model>|null
     */
    private static function climb(Model $model): ?string
    {
        $subject = $model;

        for ($depth = 0; $subject instanceof IsPartOfParent && $depth < self::MAX_DEPTH; $depth++) {
            $relation = RelationGuard::relationOf($subject, $subject->parentRelation());

            if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                return null;
            }

            $subject = $relation->getRelated();
        }

        return $subject instanceof IsPartOfParent ? null : $subject::class;
    }
}
