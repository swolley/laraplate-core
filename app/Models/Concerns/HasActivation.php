<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Contracts\IActivatableModel;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @phpstan-require-implements IActivatableModel
 */
trait HasActivation
{
    /**
     * @var string
     */
    protected static $activation_column = 'is_active';

    public static function activationColumn(): string
    {
        return static::$activation_column;
    }

    public function isActive(): bool
    {
        return $this->{static::$activation_column};
    }

    public function activate(): void
    {
        $this->{static::$activation_column} = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->{static::$activation_column} = false;
        $this->save();
    }

    protected function casts(): array
    {
        return [
            static::$activation_column => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::$activation_column), true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function inactive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::$activation_column), false);
    }

    protected function initializeHasActivation(): void
    {
        if (! in_array(static::$activation_column, $this->fillable, true)) {
            $this->fillable[] = static::$activation_column;
        }

        if (! in_array(static::$activation_column, $this->hidden, true)) {
            $this->hidden[] = static::$activation_column;
        }

        if (! isset($this->attributes[static::$activation_column])) {
            $this->attributes[static::$activation_column] = true;
        }
    }
}
