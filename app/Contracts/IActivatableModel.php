<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A model carrying an on/off flag, whose column it names itself.
 *
 * Supplied in full by {@see \Modules\Core\Models\Concerns\HasActivation}. Generic
 * code asks for this contract rather than for an Eloquent Model, so a table that
 * renders an activation toggle states the requirement it actually has.
 *
 * @method static Builder<Model&static> active()
 * @method static Builder<Model&static> inactive()
 * @method Builder<Model&static> scopeActive(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeInactive(Builder<Model&static> $query)
 */
interface IActivatableModel
{
    public static function activationColumn(): string;

    public function isActive(): bool;

    public function activate(): void;

    public function deactivate(): void;
}
