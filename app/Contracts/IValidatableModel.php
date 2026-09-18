<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A model whose rows are valid over a period, and are published, scheduled,
 * expired or draft depending on where now falls inside it.
 *
 * Supplied in full by {@see \Modules\Core\Models\Concerns\HasValidity}.
 *
 * The query scopes HasValidity declares with #[Scope]. Named here because generic
 * code holds a Builder it cannot type to a concrete model, and an intersection
 * with this contract is what tells the analyser the scopes are there.
 *
 * @method static Builder<Model&static> valid()
 * @method static Builder<Model&static> published()
 * @method static Builder<Model&static> draft()
 * @method static Builder<Model&static> scheduled()
 * @method static Builder<Model&static> expired()
 * @method static Builder<Model&static> validityOrdered()
 * @method static Builder<Model&static> validAt(Carbon $date)
 * @method static Builder<Model&static> expiredAt(Carbon $date)
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
