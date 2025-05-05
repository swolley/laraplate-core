<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Helpers\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperLicense
 */
final class License extends Model
{
    use HasFactory, HasUuids, HasValidations, HasValidity {
        getRules as protected getRulesTrait;
    }

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    public function free(Builder $query): void
    {
        $query->doesntHave('user');
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    public function occupied(Builder $query): void
    {
        $query->has('user');
    }

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
}
