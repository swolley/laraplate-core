<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Optional path accessor for models that can expose a hierarchical or derived path.
 *
 * The accessor is intentional: `$model->path` still works when a caller needs it
 * (e.g. after loading a tree with ancestors). It is deliberately NOT appended to
 * `$appends`, so list/CRUD serialization does not pay `getPath()` — and its
 * relation loads — for every row.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasPath
{
    /**
     * get path for full path.
     */
    abstract protected function getPath(): ?string;

    /** @class-property string|null $slug */
    /**
     * get prefix for full path.
     */
    protected function getPathPrefix(): string
    {
        return $this->getTable();
    }

    /**
     * get suffix for full path.
     */
    protected function getPathSuffix(): ?string
    {
        $key = $this->getKey();

        return $key ? (string) $key : null;
    }

    /**
     * get full path.
     */
    protected function getFullPath(): string
    {
        $suffix = $this->getPathSuffix();
        $prefix = $this->getPathPrefix();
        $path = $this->getPath();

        return str_replace('//', '/', $prefix . '/' . ($path ?: 'undefined') . '/' . ($this->slug ?? 'undefined') . ($suffix ? '/' . $suffix : ''));
    }

    protected function path(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getFullPath(),
        );
    }
}
