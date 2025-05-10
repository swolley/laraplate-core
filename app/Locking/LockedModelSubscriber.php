<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSending;
use Modules\Core\Locking\Exceptions\LockedModelException;

final class LockedModelSubscriber
{
    /**
     * Register the listeners for the subscriber.
     * @return array<string,string>
     */
    public function subscribe(): array
    {
        return [
            'eloquent.saving: *' => 'saving',
            'eloquent.deleting: *' => 'deleting',
            'eloquent.replicating: *' => 'replicating',
            NotificationSending::class => 'notificationSending',
        ];
    }

    /**
     * @param string $event
     * @param mixed $entity
     * @return bool
     * @phpstan-ignore-next-line
     */
    public function saving(string $event, $entity): bool
    {
        if (new Locked()->allowsModificationsOnLockedObjects()) {
            return true;
        }

        $model = $this->getModelFromPassedParams($entity);

        if (new Locked()->doesNotUseHasLocks($model)) {
            return true;
        }

        $locked = new Locked();
        $lockedAtColumnName = $locked->lockedAtColumn();

        if ($model->wasUnlocked() && $model->isDirty($lockedAtColumnName)) {
            // we are locking a model
            return true;
        }

        if ($model->wasLocked() && $model->isDirty()) {
            throw new LockedModelException('This model is locked');
        }

        return true;
    }

    /**
     * @param string $event
     * @param mixed $entity
     * @return bool
     * @phpstan-ignore-next-line
     */
    public function deleting(string $event, $entity): bool
    {
        if (new Locked()->allowsModificationsOnLockedObjects()) {
            return true;
        }
        $model = $this->getModelFromPassedParams($entity);

        $locked = new Locked();

        if ($locked->doesNotUseHasLocks($model)) {
            return true;
        }

        if ($model->wasUnlocked()) {
            return true;
        }

        throw new LockedModelException('This model is locked');
    }

    /**
     * @param string $event
     * @param mixed $entity
     * @return bool
     * @phpstan-ignore-next-line
     */
    public function replicating(string $event, $entity): bool
    {
        $locked = new Locked();

        if ($locked->allowsModificationsOnLockedObjects()) {
            return true;
        }
        $model = $this->getModelFromPassedParams($entity);

        /** @var Model $model */
        if (!$model || $locked->doesNotUseHasLocks($model)) {
            return true;
        }

        if (!$model || $model->isUnlocked()) {
            return true;
        }

        throw new LockedModelException('This model is locked');
    }

    /**
     * @param NotificationSending $event
     * @return bool
     */
    public function notificationSending(NotificationSending $event): bool
    {
        $locked = new Locked();

        if ($locked->allowsNotificationsToLockedObjects()) {
            return false;
        }
        $model = $event->notifiable;

        if ($locked->doesNotUseHasLocks($model)) {
            return true;
        }

        if ($model->isUnlocked()) {
            return true;
        }

        throw new LockedModelException('This model is locked');
    }

    /**
     * @param mixed $params
     * @return Model|null
     */
    private function getModelFromPassedParams($params): Model|null
    {
        if (is_array($params) && $params !== []) {
            return $params[0];
        }

        return null;
    }
}
