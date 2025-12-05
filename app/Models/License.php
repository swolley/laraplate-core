<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Core\Database\Factories\LicenseFactory;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasValidity;

/**
 * @mixin IdeHelperLicense
 */
final class License extends Model
{
    use HasFactory;
    use HasUuids;
    use HasValidations {
        getRules as private getRulesTrait;
    }
    use HasValidity;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
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
}
