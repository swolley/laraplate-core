<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Hooks;

trait HasWriteHooks
{
    private array $writeEvents = [];

    public function onPreInsert(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->writeEvents[EventType::PreInsert->value] ?? null;
        }

        $this->writeEvents[EventType::PreInsert->value] = $callback;

        return $this;
    }

    public function onPostInsert(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->writeEvents[EventType::PostInsert->value] ?? null;
        }

        $this->writeEvents[EventType::PostInsert->value] = $callback;

        return $this;
    }

    public function onPreUpdate(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->writeEvents[EventType::PreUpdate->value] ?? null;
        }

        $this->writeEvents[EventType::PreUpdate->value] = $callback;

        return $this;
    }

    public function onPostUpdate(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->writeEvents[EventType::PostUpdate->value] ?? null;
        }

        $this->writeEvents[EventType::PostUpdate->value] = $callback;

        return $this;
    }

    public function onPreDelete(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->writeEvents[EventType::PreDelete->value] ?? null;
        }

        $this->writeEvents[EventType::PreDelete->value] = $callback;

        return $this;
    }

    public function onPostDelete(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->writeEvents[EventType::PostDelete->value] ?? null;
        }

        $this->writeEvents[EventType::PostDelete->value] = $callback;

        return $this;
    }
}
