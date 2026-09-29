<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\IOwnerAuthorizer;
use Override;

/**
 * Lets a test decide which owners of one class the current user may see.
 */
final readonly class StubOwnerAuthorizer implements IOwnerAuthorizer
{
    /**
     * @param  class-string<Model>  $ownerClass
     * @param  list<int>  $visibleIds
     */
    public function __construct(
        private string $ownerClass,
        private array $visibleIds,
    ) {}

    #[Override]
    public function ownerType(): string
    {
        return $this->ownerClass;
    }

    #[Override]
    public function visibleOwners(): Builder
    {
        return $this->ownerClass::query()->whereKey($this->visibleIds);
    }
}
