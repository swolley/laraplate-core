<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * A model carrying an on/off flag, whose column it names itself.
 *
 * Supplied in full by {@see \Modules\Core\Models\Concerns\HasActivation}. Generic
 * code asks for this contract rather than for an Eloquent Model, so a table that
 * renders an activation toggle states the requirement it actually has.
 *
 * @method static Builder<static> active()
 * @method static Builder<static> inactive()
 */
interface IActivatableModel
{
    public static function activationColumn(): string;

    public function isActive(): bool;

    public function activate(): void;

    public function deactivate(): void;
}
