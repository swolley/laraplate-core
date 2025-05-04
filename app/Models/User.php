<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Override;
use Filament\Panel;
use Illuminate\Validation\Rule;
use Approval\Models\Modification;
use Approval\Traits\ApprovesChanges;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Observers\UserObserver;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Locking\Traits\HasLocks;
use Lab404\Impersonate\Models\Impersonate;
use Filament\Models\Contracts\FilamentUser;
use Modules\Core\Models\Pivot\ModelHasRole;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Foundation\Auth\User as BaseUser;
use Modules\Core\Database\Factories\UserFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lab404\Impersonate\Services\ImpersonateManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy([UserObserver::class])]
/**
 * @property BelongsToMany $roles
 *
 * @mixin IdeHelperUser
 */
final class User extends BaseUser implements FilamentUser
{
    use ApprovesChanges,
        HasFactory,
        HasLocks,
        HasRoles,
        HasValidations,
        HasVersions,
        Impersonate,
        Notifiable,
        SoftDeletes,
        TwoFactorAuthenticatable {
            getRules as protected getRulesTrait;
            roles as protected rolesTrait;
        }

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

        return $this->hasPermissionViaRole(Permission::findByName(($this->getConnectionName() ?? 'default') . $this->getTable() . '.impersonate'));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        switch ($panel->getId()) {
            case 'admin':
                return $this->isSuperAdmin();
            default:
                return true;
        }
    }

    public function getImpersonator(): self
    {
        return $this->isImpersonated() ? app(ImpersonateManager::class)->getImpersonator() : $this;
    }

    /**
     * @return HasMany<UserGridConfig>
     */
    public function grid_configs(): HasMany
    {
        return $this->hasMany(UserGridConfig::class);
    }

    /**
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

    protected static function scopeSuperAdmin(Builder $query): Builder
    {
        return $query->whereHas('roles', fn ($query) => $query->where('name', config('permission.roles.superadmin')));
    }

    protected static function scopeAdmin(Builder $query): Builder
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
