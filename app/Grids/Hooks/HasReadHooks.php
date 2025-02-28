<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Hooks;

trait HasReadHooks
{
    private array $readEvents = [];

    public function onPreSelect(?callable $callback = null)
    {
        if (!$callback) {
            return $this->readEvents[EventType::PRE_SELECT->value] ?? null;
        }

        $this->readEvents[EventType::PRE_SELECT->value] = $callback;

        return $this;
    }

    public function onPostSelect(?callable $callback = null)
    {
        if (!$callback) {
            return $this->readEvents[EventType::POST_SELECT->value] ?? null;
        }

        $this->readEvents[EventType::POST_SELECT->value] = $callback;

        return $this;
    }
}
