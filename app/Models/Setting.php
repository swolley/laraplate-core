<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Cache\HasCache;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Factories\SettingFactory;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Override;

/**
 * @mixin IdeHelperSetting
 */
final class Setting extends Model
{
    use HasApprovals;
    use HasCache;
    use HasFactory;
    use HasValidations {
        getRules as protected getRulesTrait;
    }
    use HasVersions;
    use SoftDeletes;

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $fillable = [
        'name',
        'value',
        'encrypted',
        'choices',
        'type',
        'group_name',
        'description',
    ];

    protected $attributes = [
        'encrypted' => false,
        'type' => 'string',
        'group_name' => 'base',
    ];

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
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
                Rule::unique('settings')->where(function ($query): void {
                    $query->where('deleted_at', null);
                }),
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('settings')->where(function ($query): void {
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

    protected function setTypeAttribute($value): void
    {
        $this->attributes['type'] = ($value instanceof SettingTypeEnum ? $value : (SettingTypeEnum::tryFrom($value)) ?? SettingTypeEnum::STRING);
    }

    #[Override]
    protected function casts()
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

    protected function requiresApprovalWhen($modifications): bool
    {
        return array_intersect(
            array_filter($this->getFillable(), fn (string $field): bool => $field !== 'description'),
            array_keys($modifications),
        ) !== [];
    }
}
