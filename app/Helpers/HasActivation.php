<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasActivation
{
    protected static $activation_column = 'is_active';

    protected function activationCasts(): array
    {
        return [
            static::$activation_column => 'boolean',
        ];
    }

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

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::$activation_column), true);
    }

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