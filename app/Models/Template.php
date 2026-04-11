<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rule;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperTemplate
 */
final class Template extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'name',
        'content',
    ];

    #[Override]
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('templates')->where(function (QueryBuilder $query): void {
                    $query->whereNull('deleted_at');
                }),
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('templates')->where(function (QueryBuilder $query): void {
                    $query->whereNull('deleted_at');
                })->ignore($this->id, 'id'),
            ],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            // 'site_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
