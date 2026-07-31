<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A model that answers to domain actions over HTTP.
 */
interface ExposesDomainActions
{
    /**
     * @return list<string>
     */
    public static function exposedDomainActions(): array;
}
