<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

/**
 * Denies every generic CRUD write operation. Use on models that may only be mutated
 * through their owning service. Implement {@see \Modules\Core\Contracts\RestrictsCrudWrites}.
 */
trait DeniesGenericCrudWrites
{
    /**
     * @return list<string>
     */
    public function deniedCrudWrites(): array
    {
        return ['insert', 'update', 'delete', 'forceDelete'];
    }
}
