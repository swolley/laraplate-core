<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\ImportRowErrorFactory;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * One row that could not be imported: its 1-based position, the field → messages
 * that explain why, and the raw mapped row. Together these back the downloadable
 * per-row failure report and let a user fix the source and re-import.
 *
 * @property int $id
 * @property int $import_session_id
 * @property int $row_number
 * @property array<string, list<string>> $errors
 * @property array<string, string>|null $raw
 *
 * @mixin \Eloquent
 * @mixin IdeHelperImportRowError
 */
final class ImportRowError extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'import_session_id',
        'row_number',
        'errors',
        'raw',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::ImportRowErrors->value;

    /**
     * @return BelongsTo<ImportSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id');
    }

    protected static function newFactory(): ImportRowErrorFactory
    {
        return ImportRowErrorFactory::new();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'errors' => 'array',
            'raw' => 'array',
        ];
    }
}
