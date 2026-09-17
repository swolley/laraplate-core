<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * Transactional integration event awaiting publication to an external transport.
 *
 */
final class OutboxEvent extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::OutboxEvents->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'occurred_at',
        'published_at',
        'publish_attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'publish_attempts' => 'integer',
        ];
    }
}
