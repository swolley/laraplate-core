<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * One node execution within a seeder orchestration run.
 *
 * Deliberately extends the base Eloquent {@see Model}, not
 * {@see \Modules\Core\Overrides\Model}: that base class bakes in
 * {@see \Modules\Core\Models\Concerns\HasVersions} and soft deletes, and the
 * ledger must stay writable even while a seeder is repairing the very
 * settings that govern those capabilities. Adding either trait here would
 * create a bootstrap dependency that fails exactly when the ledger is most
 * needed. {@see \Modules\Core\Models\OutboxEvent} follows the same pattern
 * for the same reason.
 *
 * @property int $id
 * @property string $run_id
 * @property string $node
 * @property string $status
 * @property string|null $content_hash
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property string|null $error
 * @mixin \Eloquent
 */
final class SeedRun extends Model
{
    /** @var string */
    #[Override]
    protected $table = CoreTables::SeedRuns->value;

    /** @var list<string> */
    #[Override]
    protected $fillable = [
        'run_id',
        'node',
        'status',
        'content_hash',
        'started_at',
        'finished_at',
        'error',
    ];

    /**
     * @return array<string,string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
