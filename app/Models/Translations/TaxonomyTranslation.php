<?php

declare(strict_types=1);

namespace Modules\Core\Models\Translations;

// use Illuminate\Database\Eloquent\Relations\BelongsTo;
// use Modules\Cms\Models\Category;
use Modules\Core\Helpers\HasSlug;
use Modules\Core\Overrides\Model;
use Modules\Core\Services\Translation\Definitions\ITranslated;
use Override;

/**
 * @mixin IdeHelperTaxonomyTranslation
 */
final class TaxonomyTranslation extends Model implements ITranslated
{
    use HasSlug;

    #[Override]
    protected $table = 'taxonomies_translations';

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'taxonomy_id',
        'locale',
        'name',
        'slug',
        'components',
    ];

    #[Override]
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // /**
    //  * The category that belongs to the translation.
    //  *
    //  * @return BelongsTo<Category>
    //  */
    // public function category(): BelongsTo
    // {
    //     return $this->belongsTo(Category::class);
    // }

    protected function casts(): array
    {
        return [
            'components' => 'json',
        ];
    }
}
