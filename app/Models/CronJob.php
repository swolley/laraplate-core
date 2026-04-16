<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Validation\Rule;
use Modules\Core\Casts\CronExpression as CronExpressionCast;
use Modules\Core\Database\Factories\CronJobFactory;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Modules\Core\Rules\CronExpression as CronExpressionRule;
use Override;

/**
 * @mixin IdeHelperCronJob
 */
final class CronJob extends Model
{
    // region Traits
    use HasActivation {
        HasActivation::casts as private activationCasts;
    }
    use HasLocks;
    // endregion

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
    ];

    #[Override]
    protected $attributes = [
        'parameters' => '{}',
    ];

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            'command' => ['required', 'string', 'max:255'],
            'parameters' => ['required', 'json'],
            'schedule' => ['required', new CronExpressionRule()],
            'description' => ['string', 'max:255', 'nullable'],
            'is_active' => ['boolean', 'required'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => [
                'required',
                'string',
                'max:255',
                /** @var \Illuminate\Database\Query\Builder $query */
                Rule::unique('cron_jobs')->where(static fn ($query) => $query->whereNull('deleted_at')), // @pest-ignore-type
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                /** @var \Illuminate\Database\Query\Builder $query */
                Rule::unique('cron_jobs')->where(static fn ($query) => $query->whereNull('deleted_at'))->ignore($this->id, 'id'), // @pest-ignore-type
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
            'schedule' => CronExpressionCast::class,
            'description' => 'string',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ]);
    }
}
