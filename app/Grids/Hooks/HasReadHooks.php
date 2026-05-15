<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Hooks;

trait HasReadHooks
{
    private array $readEvents = [];

    public function onPreSelect(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->readEvents[EventType::PreSelect->value] ?? null;
        }

        $this->readEvents[EventType::PreSelect->value] = $callback;

        return $this;
    }

    public function onPostSelect(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->readEvents[EventType::PostSelect->value] ?? null;
        }

        $this->readEvents[EventType::PostSelect->value] = $callback;

        return $this;
    }
}
