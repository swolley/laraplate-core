<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Search\Services\ScoutSearchConstraintApplier;

function scout_constraint_fake_builder(): object
{
    return new class
    {
        /**
         * @var list<array{method: string, field: string, value: mixed}>
         */
        public array $calls = [];

        /**
         * @var array<string, mixed>
         */
        public array $options = [];

        public function where(string $field, mixed $value): self
        {
            $this->calls[] = ['method' => 'where', 'field' => $field, 'value' => $value];

            return $this;
        }

        public function whereIn(string $field, array $value): self
        {
            $this->calls[] = ['method' => 'whereIn', 'field' => $field, 'value' => $value];

            return $this;
        }

        public function whereNotIn(string $field, array $value): self
        {
            $this->calls[] = ['method' => 'whereNotIn', 'field' => $field, 'value' => $value];

            return $this;
        }

        public function orderBy(string $field, string $direction): self
        {
            $this->calls[] = ['method' => 'orderBy', 'field' => $field, 'value' => $direction];

            return $this;
        }
    };
}

it('applies the portable scout search constraint subset', function (): void {
    $builder = scout_constraint_fake_builder();
    $model = new class extends Model
    {
        protected $table = 'users';
    };

    (new ScoutSearchConstraintApplier())->apply(
        builder: $builder,
        model: $model,
        filters: new FiltersGroup([
            new Filter('users.id', 10, FilterOperator::Equals),
            new FiltersGroup([
                new Filter('status', ['draft', 'published'], FilterOperator::In),
                new Filter('lang', 'it', FilterOperator::NotEquals),
            ]),
        ]),
        sort: [new Sort('users.name', SortDirection::Desc)],
    );

    expect($builder->calls)->toBe([
        ['method' => 'where', 'field' => 'id', 'value' => 10],
        ['method' => 'whereIn', 'field' => 'status', 'value' => ['draft', 'published']],
        ['method' => 'whereNotIn', 'field' => 'lang', 'value' => ['it']],
        ['method' => 'orderBy', 'field' => 'name', 'value' => 'desc'],
    ]);
});

it('records advanced portable filters for engine-owned translation', function (): void {
    $builder = scout_constraint_fake_builder();
    $model = new class extends Model
    {
        protected $table = 'users';
    };

    (new ScoutSearchConstraintApplier())->apply(
        builder: $builder,
        model: $model,
        filters: new FiltersGroup([
            new Filter('id', 10, FilterOperator::GreatEquals),
            new Filter('id', 20, FilterOperator::Less),
            new Filter('created_at', ['2024-01-01', '2024-01-31'], FilterOperator::Between),
            new FiltersGroup([
                new Filter('status', 'draft', FilterOperator::Equals),
                new Filter('status', 'published', FilterOperator::Equals),
            ], WhereClause::Or),
        ]),
    );

    expect($builder->calls)->toBe([])
        ->and($builder->options['advanced_filters'])->toMatchArray([
            'operator' => 'and',
            'filters' => [
                ['field' => 'id', 'operator' => '>=', 'value' => 10],
                ['field' => 'id', 'operator' => '<', 'value' => 20],
                ['field' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-01-31']],
                [
                    'operator' => 'or',
                    'filters' => [
                        ['field' => 'status', 'operator' => '=', 'value' => 'draft'],
                        ['field' => 'status', 'operator' => '=', 'value' => 'published'],
                    ],
                ],
            ],
        ]);
});

it('rejects non portable search filters and relation path sorts', function (FiltersGroup $filters, array $sort, string $message): void {
    $builder = scout_constraint_fake_builder();
    $model = new class extends Model
    {
        protected $table = 'users';
    };

    expect(fn () => (new ScoutSearchConstraintApplier())->apply($builder, $model, $filters, $sort))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'like filter' => [
        new FiltersGroup([new Filter('title', 'needle', FilterOperator::Like)]),
        [],
        'Search filters must be applied by the search engine to keep pagination consistent.',
    ],
    'relation path filter' => [
        new FiltersGroup([new Filter('author.name', 'Alice')]),
        [],
        'Search filters must be applied by the search engine to keep pagination consistent.',
    ],
    'relation path sort' => [
        new FiltersGroup(),
        [new Sort('author.name')],
        'Search sort must be applied by the search engine to keep pagination consistent.',
    ],
]);
