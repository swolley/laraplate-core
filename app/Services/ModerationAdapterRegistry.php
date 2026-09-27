<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
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

    /**
     * Models that have a registered adapter, so automated moderation can be offered for them.
     *
     * @return list<class-string<Model>>
     */
    public function modelClasses(): array
    {
        return array_values(array_unique(array_map(
            static fn (ModerationAdapter $adapter): string => $adapter->modelClass(),
            $this->adapters,
        )));
    }

    public function supports(Modification $modification): bool
    {
        return $this->resolve($modification) !== null;
    }

    public function build(Modification $modification): ModerationRequest
    {
        $adapter = $this->resolve($modification);

        if ($adapter === null) {
            throw new InvalidArgumentException(
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
