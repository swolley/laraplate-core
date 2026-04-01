<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\License;

final class QueryBuilderOwner extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public function getNickAttribute(): string
    {
        return (string) $this->username;
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id');
    }

    /**
     * @return array<string, array{columns?: array<int, string>, relations?: array<int, string>}>
     */
    public function crudComputedDependencies(): array
    {
        return [
            'nick' => [
                'columns' => ['username'],
                'relations' => ['license'],
            ],
        ];
    }
}