<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\DTOs\GraphRelation;
use Modules\Core\Support\RelationGuard;
use ReflectionMethod;

final class GraphRelationInspector
{
    public function inspect(Model $model, string $relationName): GraphRelation
    {
        if (! method_exists($model, $relationName)) {
            throw ValidationException::withMessages([
                'relations' => sprintf("Relation '%s' does not exist on '%s'.", $relationName, $model::class),
            ]);
        }

        $method = new ReflectionMethod($model, $relationName);

        if ($method->getNumberOfRequiredParameters() > 0) {
            throw ValidationException::withMessages([
                'relations' => sprintf("Relation '%s' cannot be traversed because it requires parameters.", $relationName),
            ]);
        }

        // The relation name comes from the request and the model is a loaded record: only a method declared as
        // returning a relation is called, never one such as `delete` that would act on the record.
        $relation = RelationGuard::relationOf($model, $relationName);

        if (! $relation instanceof Relation) {
            throw ValidationException::withMessages([
                'relations' => sprintf("Method '%s' is not an Eloquent relation.", $relationName),
            ]);
        }

        $related = $relation->getRelated();

        return new GraphRelation(
            name: $relationName,
            relation: $relation,
            relatedClass: $related::class,
            // A MorphToMany is a BelongsToMany.
            isMultiple: $relation instanceof HasMany
                || $relation instanceof BelongsToMany
                || $relation instanceof MorphMany,
            isMorphTo: $relation instanceof MorphTo,
        );
    }
}
