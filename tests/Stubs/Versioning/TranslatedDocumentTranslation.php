<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IsPartOfParent;
use Modules\Core\Models\Concerns\RecordsChangesOnOwner;
use Modules\Core\Services\Translation\Definitions\ITranslated;

/**
 * The translation of {@see TranslatedDocument}: its changes are rows of the document's history.
 */
final class TranslatedDocumentTranslation extends Model implements IsPartOfParent, ITranslated
{
    use RecordsChangesOnOwner;

    public const string TABLE = 'core_test_translated_document_translations';

    protected $table = self::TABLE;

    protected $fillable = [
        'translated_document_id',
        'locale',
        'title',
        'body',
    ];

    public function parentRelation(): string
    {
        return 'document';
    }

    /**
     * @return BelongsTo<TranslatedDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(TranslatedDocument::class, 'translated_document_id');
    }
}
