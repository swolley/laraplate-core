<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Integration\Search;

use Illuminate\Database\Eloquent\Model;

class EnsembleSearchPaginatorTestModel extends Model
{
    public static ?EnsembleSearchPaginatorTestBuilder $lastBuilder = null;

    protected $guarded = [];

    /**
     * @return EnsembleSearchPaginatorTestBuilder
     */
    public static function search($query = '', $callback = null): mixed
    {
        $items = collect(range(1, 15))->map(static function (int $id): self {
            $model = new self();
            $model->forceFill(['id' => $id, '_score' => 1.0]);
            $model->exists = true;

            return $model;
        });

        return self::$lastBuilder = new EnsembleSearchPaginatorTestBuilder(new self(), (string) $query, $items, 27);
    }

    public function searchableUsing(): object
    {
        return new class
        {
            public function getName(): string
            {
                return 'fake';
            }
        };
    }
}
