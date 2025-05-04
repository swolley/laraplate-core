<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Cache\HasCache;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Database\Factories\PermissionFactory;
use Spatie\Permission\Models\Permission as ModelsPermission;

/**
 * @mixin IdeHelperPermission
 */
final class Permission extends ModelsPermission
{
    use HasCache, HasValidations {
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
        'guard_name',
    ];

    protected $guarded = [
        'id',
        'connection_name',
        'table_name',
    ];

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $hidden = [
        'pivot',
    ];

    protected $append = [
        'action',
    ];

    protected $attributes = [
        'guard_name' => 'web',
    ];

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->guarded = array_merge($this->guarded ?? [], [
            'connection_name',
            'table_name',
        ]);
    }

    public function getRules()
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
            'guard_name' => ['string', 'max:255'],
            'description' => ['string', 'max:255', 'nullable'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'regex:/^\\w+\\.\\w+\\.\\w+$/', 'unique:permissions,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'regex:/^\\w+\\.\\w+\\.\\w+$/', 'unique:permissions,name,' . $this->id],
        ]);

        return $rules;
    }

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }

    protected function getActionAttribute(): ?ActionEnum
    {
        if ($this->name === null) {
            return null;
        }
        $splitted = explode('.', $this->name);

        return ActionEnum::tryFrom(array_pop($splitted));
    }
}
