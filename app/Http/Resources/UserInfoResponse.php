<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\Permission;
use Override;

final class UserInfoResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    #[Override]
    public function toArray(\Illuminate\Http\Request $request): array
    {
        if ($this->resource) {
            $permissions = [];

            foreach ($this->resource->isSuperAdmin() ? Permission::all() : $this->resource->getAllPermissions() as $permission) {
                $guard_key = $permission->guard_name;

                if (! isset($permissions[$guard_key])) {
                    $permissions[$guard_key] = [$permission->name];
                } else {
                    $permissions[$guard_key][] = $permission->name;
                }
            }

            $roles = $this->resource->roles->map(static fn (object $role) => $role->name);

            return [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'username' => $this->resource->username,
                'email' => $this->resource->email,
                'groups' => $roles,
                'canImpersonate' => $this->resource->canImpersonate(),
                'permissions' => $permissions,
            ];
        }

        return [
            'id' => 'anonymous',
            'name' => 'anonymous',
            'username' => 'anonymous',
            'email' => 'anonymous',
            'groups' => [],
            'canImpersonate' => false,
            'permissions' => [],
        ];
    }
}
