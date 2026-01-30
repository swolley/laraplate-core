<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Pivot\ModelHasRole;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

final class ModelHasRoleObserver
{
    /**
     * Prevent assigning any role other than superadmin to a user who already has the superadmin role.
     */
    public function creating(ModelHasRole $modelHasRole): void
    {
        $model = $this->resolveModel($modelHasRole);

        if (! $model instanceof User) {
            return;
        }

        if (! $model->hasRole(config('permission.roles.superadmin'))) {
            return;
        }

        $superadmin_role_id = Role::query()
            ->where('name', config('permission.roles.superadmin'))
            ->value('id');

        if ($superadmin_role_id === null) {
            return;
        }

        if ((int) $modelHasRole->role_id === (int) $superadmin_role_id) {
            return;
        }

        throw ValidationException::withMessages([
            'roles' => [__('A user with the superadmin role cannot have other roles assigned.')],
        ]);
    }

    /**
     * @return object|null
     */
    private function resolveModel(ModelHasRole $modelHasRole): ?object
    {
        $model_type = $modelHasRole->model_type;
        $model_id = $modelHasRole->model_id;

        if (! $model_type || ! $model_id) {
            return null;
        }

        if (! class_exists($model_type)) {
            return null;
        }

        return $model_type::find($model_id);
    }
}
