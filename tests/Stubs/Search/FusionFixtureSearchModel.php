<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * Searchable stand-in whose keyword, vector and hybrid strategies return different, fixed
 * rankings and scores, so the ensemble fusion and reranking math can be pinned exactly
 * without a search engine. The strategy is inferred by {@see FusionFixtureSearchBuilder}.
 */
final class FusionFixtureSearchModel extends Model
{
    protected $guarded = [];

    /**
     * @param  string  $query
     * @param  callable|null  $callback
     */
    public static function search($query = '', $callback = null): FusionFixtureSearchBuilder
    {
        return new FusionFixtureSearchBuilder(new self(), (string) $query);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function hit(array $attributes): self
    {
        $model = new self();
        $model->forceFill($attributes);
        $model->exists = true;

        return $model;
    }

    public function searchableUsing(): FusionFixtureEngineName
    {
        return new FusionFixtureEngineName();
    }
}
