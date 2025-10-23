<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Core\Cache\HasCache;
use Modules\Core\Database\Factories\RoleFactory;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Pivot\ModelHasRole;
use Override;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role as BaseRole;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @mixin IdeHelperRole
 */
final class Role extends BaseRole
{
    use HasCache;
    use HasFactory;
    use HasLocks;
    use HasRecursiveRelationships;
    use HasValidations;
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
        'guard_name',
        'description',
    ];

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $hidden = [
        'parent_id',
        'pivot',
    ];

    #[Override]
    public function users(): BelongsToMany
    {
        return parent::users()->using(ModelHasRole::class);
    }

    #[Override]
    public function getAllPermissions(): Collection
    {
        /** @psalm-suppress UndefinedThisPropertyFetch */
        $permissions = $this->permissions;

        /**
         * @psalm-suppress UndefinedThisPropertyFetch
         *
         * @var Role $parent
         */
        foreach ($this->ancestors as $parent) {
            $permissions = $permissions->merge($parent->permissions);
        }

        return $permissions->sort()->values();
    }

    /**
     * @throws PermissionDoesNotExist
     * @throws GuardDoesNotMatch
     */
    public function hasPermission(string $permission): bool
    {
        $has_permission = parent::hasPermissionTo($permission);

        if ($has_permission) {
            return true;
        }

        /**
         * @psalm-suppress UndefinedThisPropertyFetch
         *
         * @var Role $parent
         */
        foreach ($this->ancestors as $parent) {
            if ($parent->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
            'guard_name' => ['string', 'max:255'],
            'description' => ['string', 'max:255', 'nullable'],
            // 'locked_at' => ['date', 'nullable'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(function ($query): void {
                    $query->where('deleted_at', null);
                }),
            ],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles')->where(function ($query): void {
                    $query->where('deleted_at', null);
                })->ignore($this->id, 'id'),
            ],
        ]);

        return $rules;
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
