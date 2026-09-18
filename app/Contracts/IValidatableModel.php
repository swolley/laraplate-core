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
 * @method static Builder<Model&static> expiring(?int $within_hours = null)
 * @method static Builder<Model&static> validityOrdered()
 * @method static Builder<Model&static> validAt(CarbonInterface $date)
 * @method static Builder<Model&static> expiredAt(CarbonInterface $date)
 *
 * Named again with the scope prefix, which is the form Larastan looks for when
 * the call is made on a Builder rather than on the model: BuilderHelper reads
 * `scope` . ucfirst($method) off the method tags of every class in the builder's
 * model type, this interface included. Without them, generic code holding a
 * Builder<Model&IValidatableModel> gets "undefined method" for each one.
 *
 * @method Builder<Model&static> scopeValid(Builder<Model&static> $query)
 * @method Builder<Model&static> scopePublished(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeDraft(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeScheduled(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeExpired(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeExpiring(Builder<Model&static> $query, ?int $within_hours = null)
 * @method Builder<Model&static> scopeValidityOrdered(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeValidAt(Builder<Model&static> $query, CarbonInterface $date)
 * @method Builder<Model&static> scopeExpiredAt(Builder<Model&static> $query, CarbonInterface $date)
 * @method Builder<Model&static> scopeWithValidityFilter(Builder<Model&static> $query, CarbonInterface $date)
 */
interface IValidatableModel
{
    /**
     * The default window, in hours, for the `expiring` scope on this model.
     */
    public static function expiringWithinHours(): int;

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
