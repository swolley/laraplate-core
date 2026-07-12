<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\DTOs\GraphRelation;
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

        $relation = $model->{$relationName}();

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
            isMultiple: $relation instanceof HasMany
                || $relation instanceof BelongsToMany
                || $relation instanceof MorphMany
                || $relation instanceof MorphToMany,
            isMorphTo: $relation instanceof MorphTo,
        );
    }
}
