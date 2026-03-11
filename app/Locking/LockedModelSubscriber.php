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
     *
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

    public function saving(string $event, object|array $entity): bool
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

        throw_if($model->wasLocked() && $model->isDirty(), LockedModelException::class, 'This model is locked');

        return true;
    }

    public function deleting(string $event, object|array $entity): bool
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

    public function replicating(string $event, object|array $entity): bool
    {
        $locked = new Locked();

        if ($locked->allowsModificationsOnLockedObjects()) {
            return true;
        }

        $model = $this->getModelFromPassedParams($entity);

        /** @var Model $model */
        if (! $model instanceof Model || $locked->doesNotUseHasLocks($model)) {
            return true;
        }

        if ($model->isUnlocked()) {
            return true;
        }

        throw new LockedModelException('This model is locked');
    }

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

    private function getModelFromPassedParams(object|array $params): ?Model
    {
        if (is_array($params) && $params !== [] && $params[0] instanceof Model) {
            return $params[0];
        }

        if (is_object($params) && $params instanceof Model) {
            return $params;
        }

        return null;
    }
}
