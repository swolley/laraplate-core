<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Override;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class CustomSoftDeletingScope extends SoftDeletingScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    #[Override]
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getQualifiedIsDeletedColumn(), false);
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    #[Override]
    protected function addWithoutTrashed(Builder $builder): void
    {
        $builder->macro('withoutTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNull(
                $model->getQualifiedIsDeletedColumn(),
            );

            return $builder;
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    #[Override]
    protected function addOnlyTrashed(Builder $builder): void
    {
        $builder->macro('onlyTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNotNull(
                $model->getQualifiedIsDeletedColumn(),
            );

            return $builder;
        });
    }
}
