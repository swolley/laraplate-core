<?php

declare(strict_types=1);

namespace App\Models\Translations;

use App\Models\Author;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\Core\Services\Translation\Definitions\ITranslated;
use Override;

/**
 * Test-only translation of {@see Author}: `components` is its only translatable field.
 */
final class AuthorTranslation extends Model implements ITranslated
{
    /**
     * @var string
     */
    #[Override]
    protected $table = Author::TRANSLATIONS_TABLE;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'author_id',
        'locale',
        'components',
    ];

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'components' => 'json',
        ];
    }
}
