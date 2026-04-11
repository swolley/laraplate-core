<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Cache\HasCache;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperUserGridConfig
 */
final class UserGridConfig extends Model
{
    use HasCache;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'user_id',
        'grid_name',
        'layout_name',
        'is_public',
        'config',
    ];

    /**
     * The user that belongs to the user grid config.
     *
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(user_class());
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            'user_id' => ['integer', 'exists:users,id'],
            'grid_name' => ['required', 'max:255'],
            'layout_name' => ['required', 'max:255'],
            'is_public' => ['boolean', 'required'],
            'config' => ['required'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_public' => 'boolean',
            'config' => 'json',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
