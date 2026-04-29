<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\Rule;
use Modules\Core\Database\Factories\LicenseFactory;
use Modules\Core\Helpers\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Override;

/**
 * @mixin IdeHelperLicense
 */
final class License extends Model
{
    use HasFactory;
    use HasValidations;
    use HasVersions;
    use HasValidity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'uuid',
    ];

    #[Override]
    protected $casts = [
        'is_active' => 'boolean',
    ];

    private bool $softDeletesEnabled = false;

    /**
     * The user that belongs to the license.
     *
     * @return HasOne<User>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            'uuid' => ['required', 'uuid', Rule::unique('licenses', 'uuid')->ignore($this->getKey())],
            'valid_from' => ['date'],
            'valid_to' => ['nullable', 'date'],
        ]);

        return $rules;
    }

    protected static function newFactory(): LicenseFactory
    {
        return LicenseFactory::new();
    }

    #[Scope]
    protected function free(Builder $query): void
    {
        $query->doesntHave('user');
    }

    #[Scope]
    protected function occupied(Builder $query): void
    {
        $query->has('user');
    }

    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->whereNotNull('valid_to')
            ->where('valid_to', '<', today());
    }
}
