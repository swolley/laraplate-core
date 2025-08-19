<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\Sort;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Rules\QueryBuilder;
use Override;

/**
 * @mixin IdeHelperACL
 */
final class ACL extends Model
{
    use HasValidations, HasVersions, SoftDeletes {
        getRules as protected getRulesTrait;
    }

    protected $table = 'acls';

    protected $fillable = [
        'permission_id',
        'filters',      // Stored as JSON
        'sort',         // Optional: stored as JSON
        'description',   // Optional: human readable description
    ];

    /**
     * The permission that belongs to the ACL.
     *
     * @return BelongsTo<Permission>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function getRules(): array
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

    #[Scope]
    protected function forPermission(Builder $query, $permission_id): Builder
    {
        return $query->where('permission_id', $permission_id);
    }

    #[Override]
    protected function casts()
    {
        return [
            'filters' => FiltersGroup::class,
            'sort' => Sort::class,
        ];
    }
}
