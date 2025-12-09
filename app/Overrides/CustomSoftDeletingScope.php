<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;

final class CustomSoftDeletingScope extends SoftDeletingScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    #[Override]
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getQualifiedIsDeletedColumn(), false);
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param  Builder<Model>  $builder
     */
    #[Override]
    protected function addWithoutTrashed(Builder $builder): void
    {
        $builder->macro('withoutTrashed', function (Builder $builder): Builder {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->where($model->getQualifiedIsDeletedColumn(), false);

            return $builder;
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  Builder<Model>  $builder
     */
    #[Override]
    protected function addOnlyTrashed(Builder $builder): void
    {
        $builder->macro('onlyTrashed', function (Builder $builder): Builder {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->where($model->getQualifiedIsDeletedColumn(), true);

            return $builder;
        });
    }
}
