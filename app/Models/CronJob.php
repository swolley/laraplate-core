<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Validation\Rule;
use Modules\Core\Casts\CronExpression as CronExpressionCast;
use Modules\Core\Contracts\IActivatableModel;
use Modules\Core\Contracts\ILockableModel;
use Modules\Core\Database\Factories\CronJobFactory;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Concerns\HasActivation;
use Modules\Core\Overrides\Model;
use Modules\Core\Rules\CronExpression as CronExpressionRule;
use Modules\Core\Rules\CronParametersList;
use Override;

final class CronJob extends Model implements IActivatableModel, ILockableModel
{
    // region Traits
    use HasActivation {
        HasActivation::casts as private activationCasts;
    }
    use HasLocks;
    // endregion

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::CronJobs->value;

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    #[Override]
    protected $fillable = [
        'name',
        'command',
        'parameters',
        'schedule',
        'description',
        'on_one_server',
        'without_overlapping',
    ];

    #[Override]
    protected $attributes = [
        'parameters' => '[]',
        'on_one_server' => true,
        'without_overlapping' => true,
    ];

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            // The name of the command only: its arguments go in `parameters`, where Laravel escapes them.
            'command' => ['required', 'string', 'max:255', 'regex:/^\S+$/'],
            'parameters' => ['required', new CronParametersList()],
            'schedule' => ['required', new CronExpressionRule()],
            'description' => ['string', 'max:255', 'nullable'],
            'is_active' => ['boolean', 'required'],
            'on_one_server' => ['boolean'],
            'without_overlapping' => ['boolean'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => [
                'required',
                'string',
                'max:255',
                /** @var \Illuminate\Database\Query\Builder $query */
                Rule::unique(CoreTables::CronJobs->value)->where(static fn ($query) => $query->whereNull('deleted_at')), // @pest-ignore-type
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                /** @var \Illuminate\Database\Query\Builder $query */
                Rule::unique(CoreTables::CronJobs->value)->where(static fn ($query) => $query->whereNull('deleted_at'))->ignore($this->id, 'id'), // @pest-ignore-type
            ],
        ]);

        return $rules;
    }

    protected static function newFactory(): CronJobFactory
    {
        return CronJobFactory::new();
    }

    protected function casts(): array
    {
        return array_merge($this->activationCasts(), [
            'name' => 'string',
            'command' => 'string',
            'parameters' => 'json',
            'on_one_server' => 'boolean',
            'without_overlapping' => 'boolean',
            'schedule' => CronExpressionCast::class,
            'description' => 'string',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ]);
    }
}
