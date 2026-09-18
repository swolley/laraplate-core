<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Schemas\Schema;
use LogicException;

/**
 * Typed access to a Filament page's own `form` schema.
 *
 * Filament resolves `$this->form` through
 * {@see \Filament\Schemas\Concerns\ResolvesDynamicLivewireProperties::__get},
 * which no static analyser can follow: the property is reported as undefined and
 * every Schema call on it as a call on an unknown type. `getSchema('form')` is the
 * same lookup with a declared return type, so the page keeps the behaviour and
 * gains the signature.
 */
trait HasPageForm
{
    /**
     * The page's `form` schema.
     *
     * Absent only when the page declares no `form()` method, which is a coding
     * error rather than a runtime state, hence the exception over a null return.
     */
    protected function pageForm(): Schema
    {
        $schema = $this->getSchema('form');

        if (! $schema instanceof Schema) {
            throw new LogicException(static::class . ' has no "form" schema.');
        }

        return $schema;
    }
}
