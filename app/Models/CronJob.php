<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Modules\Core\Casts\CronExpression as CronExpressionCast;
use Modules\Core\Database\Factories\CronJobFactory;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Rules\CronExpression as CronExpressionRule;
use Override;

/**
 * @mixin IdeHelperCronJob
 */
final class CronJob extends Model
{
    use HasFactory, HasLocks, HasValidations, HasVersions, SoftDeletes {
        getRules as protected getRulesTrait;
    }

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
        'is_active',
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
                Rule::unique('cron_jobs')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('cron_jobs')->where(fn ($query) => $query->whereNull('deleted_at'))->ignore($this->id, 'id'),
            ],
        ]);

        return $rules;
    }

    protected static function newFactory(): CronJobFactory
    {
        return CronJobFactory::new();
    }

    #[Override]
    protected static function booted(): void
    {
        self::saved(function (CronJob $cronJob): void {
            Cache::forget($cronJob->getTable());
        });
        self::deleted(function (CronJob $cronJob): void {
            Cache::forget($cronJob->getTable());
        });
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'name' => 'string',
            'command' => 'string',
            'parameters' => 'json',
            'schedule' => CronExpressionCast::class,
            'description' => 'string',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
