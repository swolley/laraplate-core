<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Casts\CronExpression as CronExpressionCast;
use Modules\Core\Database\Factories\CronJobFactory;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Rules\CronExpression as CronExpressionRule;

/**
 * @mixin IdeHelperCronJob
 */
final class CronJob extends Model
{
    // region Traits
    use HasActivation {
        HasActivation::casts as private activationCasts;
    }
    use HasFactory;
    use HasLocks;
    use HasValidations {
        getRules as private getRulesTrait;
    }
    use HasVersions;
    use SoftDeletes;
    // endregion

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $fillable = [
        'name',
        'command',
        'parameters',
        'schedule',
        'description',
    ];

    protected $attributes = [
        'parameters' => '{}',
    ];

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
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
                Rule::unique('cron_jobs')->where(static fn ($query) => $query->whereNull('deleted_at')),
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('cron_jobs')->where(static fn ($query) => $query->whereNull('deleted_at'))->ignore($this->id, 'id'),
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
