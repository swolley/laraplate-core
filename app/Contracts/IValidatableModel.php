<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A model whose rows are valid over a period, and are published, scheduled,
 * expired or draft depending on where now falls inside it.
 *
 * Supplied in full by {@see \Modules\Core\Models\Concerns\HasValidity}.
 *
 * @method static Builder<static> valid()
 * @method static Builder<static> published()
 */
interface IValidatableModel
{
    public static function validFromKey(): string;

    public static function validToKey(): string;

    public function isValid(?CarbonInterface $date = null): bool;

    public function isPublished(?CarbonInterface $date = null): bool;

    public function isExpired(): bool;

    public function isDraft(): bool;

    public function isScheduled(): bool;

    public function publish(?Carbon $valid_from = null, ?Carbon $valid_to = null): void;

    public function unpublish(): void;
}
