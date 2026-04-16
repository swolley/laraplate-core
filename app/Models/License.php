<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Core\Database\Factories\LicenseFactory;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperLicense
 */
final class License extends Model
{
    use HasUuids;
    use HasValidity;

    #[Override]
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [];

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
