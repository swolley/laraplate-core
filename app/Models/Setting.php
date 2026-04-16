<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Cache\HasCache;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Factories\SettingFactory;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Observers\SettingObserver;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperSetting
 */
#[ObservedBy(SettingObserver::class)]
final class Setting extends Model
{
    use HasApprovals;
    use HasCache;

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    #[Override]
    protected $fillable = [
        'name',
        'value',
        'encrypted',
        'choices',
        'type',
        'group_name',
        'description',
    ];

    #[Override]
    protected $attributes = [
        'encrypted' => false,
        'type' => 'string',
        'group_name' => 'base',
    ];

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            'encrypted' => ['boolean', 'required'],
            'choices' => ['sometimes', 'nullable'],
            'choices.*' => ['filled'],
            'type' => ['required', new Enum(SettingTypeEnum::class)],
            'group_name' => ['string', 'required', 'max:50'],
            'description' => ['string', 'max:255', 'nullable'],
            'locked_at' => ['date', 'nullable'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => [
                'required',
                'string',
                'max:50',
                /** @var \Illuminate\Database\Query\Builder $query */
                Rule::unique('settings')->where(function ($query): void { // @pest-ignore-type
                    $query->where('deleted_at', null);
                }),
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:50',
                /** @var \Illuminate\Database\Query\Builder $query */
                Rule::unique('settings')->where(function ($query): void { // @pest-ignore-type
                    $query->where('deleted_at', null);
                })->ignore($this->id, 'id'),
            ],
        ]);

        return $rules;
    }

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    protected function setTypeAttribute(mixed $value): void
    {
        $this->attributes['type'] = ($value instanceof SettingTypeEnum ? $value : (SettingTypeEnum::tryFrom($value)) ?? SettingTypeEnum::STRING);
    }

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'encrypted' => 'boolean',
            'choices' => 'array',
            'type' => SettingTypeEnum::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function requiresApprovalWhen(array $modifications): bool
    {
        return array_intersect(
            array_filter($this->getFillable(), static fn (string $field): bool => $field !== 'description'),
            array_keys($modifications),
        ) !== [];
    }
}
