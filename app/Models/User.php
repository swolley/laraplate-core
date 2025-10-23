<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Modification;
use Approval\Traits\ApprovesChanges;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Lab404\Impersonate\Exceptions\InvalidUserProvider;
use Lab404\Impersonate\Exceptions\MissingUserProvider;
use Lab404\Impersonate\Services\ImpersonateManager;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Modules\Core\Database\Factories\UserFactory;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Pivot\ModelHasRole;
use Modules\Core\Observers\UserObserver;
use Override;
use Spatie\Permission\Traits\HasRoles;

#[ObservedBy([UserObserver::class])]
/**
 * @property BelongsToMany $roles
 * @mixin IdeHelperUser
 */
class User extends BaseUser implements FilamentUser, MustVerifyEmail
{
    use ApprovesChanges;
    use HasFactory;
    use HasLocks;
    use HasRoles {
        HasRoles::getPermissionsViaRoles as protected getPermissionsViaRolesTrait;
    }
    use HasValidations;
    use HasVersions;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'lang',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pivot',
        'license_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'last_login_at',
        'updated_at',
        'created_at',
        'email_verified_at',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->can('*', ['guard' => $panel->getAuthGuard()]);
    }

    public function isGuest(): bool
    {
        return ! property_exists($this, 'email') || $this->email === null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('permission.roles.superadmin'));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(config('permission.roles.admin'));
    }

    public function canImpersonate(): bool
    {
        /** @phpstan-ignore staticMethod.notFound */
        if ($this->isSuperAdmin()) {
            return true;
        }

        try {
            return $this->hasPermissionViaRole(Permission::findByName(($this->getConnectionName() ?? 'default') . $this->getTable() . '.impersonate'));
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->isSuperAdmin();
    }

    /**
     * returns the impersonator user.
     *
     * @throws BindingResolutionException
     * @throws MissingUserProvider
     * @throws InvalidUserProvider
     * @throws ModelNotFoundException
     */
    public function getImpersonator(): self
    {
        return $this->isImpersonated() ? app(ImpersonateManager::class)->getImpersonator() : $this;
    }

    /**
     * returns the saved custom grid configs for the user.
     *
     * @return HasMany<UserGridConfig>
     */
    public function grid_configs(): HasMany
    {
        return $this->hasMany(UserGridConfig::class);
    }

    /**
     * returns the license currently related to the user.
     *
     * @return BelongsTo<License>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * @return BelongsToMany<Role>
     */
    public function roles(): BelongsToMany
    {
        return $this->rolesTrait()->using(ModelHasRole::class);
    }

    public function getPermissionsViaRoles(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()->get()->sort()->values();
        }

        return $this->getPermissionsViaRolesTrait();
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
            'lang' => ['nullable', 'in:' . implode(',', translations())],
            'locked_at' => ['nullable', 'date'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->where(function ($query): void {
                    $query->where('deleted_at', null);
                }),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users'),
            ],
            'password' => [Password::required()],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['nullable', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users')->where(function ($query): void {
                    $query->where('deleted_at', null);
                })->ignore($this->id, 'id'),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users')->where(function ($query): void {
                    $query->where('deleted_at', null);
                })->ignore($this->id, 'id'),
            ],
            'password' => ['nullable', Password::default()],
        ]);

        return $rules;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    #[Scope]
    protected static function superAdmin(Builder $query): Builder
    {
        return $query->whereHas('roles', fn ($query) => $query->where('name', config('permission.roles.superadmin')));
    }

    #[Scope]
    protected static function admin(Builder $query): Builder
    {
        return $query->whereHas('roles', fn ($query) => $query->where('name', config('permission.roles.admin')));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function authorizedToApprove(Modification $mod): bool
    {
        return $this->can(($this->getConnectionName() ?? 'default') . $mod->modifiable->getTable() . '.approve');
    }

    protected function authorizedToDisapprove(Modification $mod): bool
    {
        return $this->can(($this->getConnectionName() ?? 'default') . $mod->modifiable->getTable() . '.disapprove');
    }

    protected function getDefaultGuardName(): string
    {
        return 'web';
    }
}
