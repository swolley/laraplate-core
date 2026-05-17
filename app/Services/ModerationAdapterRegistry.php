<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Contracts\ModerationAdapter;
use Modules\Core\Data\ModerationRequest;
use Modules\Core\Models\Modification;

final class ModerationAdapterRegistry
{
    /**
     * @var list<ModerationAdapter>
     */
    private array $adapters = [];

    public function register(ModerationAdapter $adapter): void
    {
        $this->adapters[] = $adapter;
    }

    public function supports(Modification $modification): bool
    {
        return $this->resolve($modification) !== null;
    }

    public function build(Modification $modification): ModerationRequest
    {
        $adapter = $this->resolve($modification);

        if ($adapter === null) {
            throw new \InvalidArgumentException(
                'No moderation adapter registered for modification #' . $modification->getKey(),
            );
        }

        return $adapter->build($modification);
    }

    public function resolve(Modification $modification): ?ModerationAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($modification)) {
                return $adapter;
            }
        }

        return null;
    }
}
