<?php

namespace Modules\Core\Models;

use Modules\Core\Casts\Sort;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Rules\QueryBuilder;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperACL
 */
class ACL extends Model
{
    use HasVersions, SoftDeletes, HasValidations {
        getRules as protected getRulesTrait;
    }

    protected $fillable = [
        'permission_id',
        'filters',      // Stored as JSON
        'sort',         // Optional: stored as JSON
        'description'   // Optional: human readable description
    ];

    #[\Override]
    protected function casts()
    {
        return [
            'filters' => FiltersGroup::class,
            'sort' => Sort::class,
        ];
    }

    /**
     * The permission that belongs to the ACL.
     * @return BelongsTo<Permission>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function scopeForPermission($query, $permission_id)
    {
        $query->where('permission_id', $permission_id);
    }

    public function getRules()
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
            'permission_id' => ['required', 'exists:permissions,id'],
            'filters' => [new QueryBuilder()],
            'sort.*.property' => ['string'],
            'sort.*.direction' => ['in:asc,desc,ASC,DESC'],
            'description' => ['string', 'max:255', 'nullable'],
        ]);
        return $rules;
    }
}
