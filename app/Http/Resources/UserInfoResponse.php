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
                // The user's explicit language preference (null when unset — the
                // caller falls back to the browser / app default itself).
                'lang' => $this->resource->lang,
                'groups' => $roles,
                'canImpersonate' => $this->resource->canImpersonate(),
                'permissions' => $permissions,
                // Whether the onboarding flow still applies, and the server-persisted
                // UI preferences the SPA rehydrates its chrome from.
                'isFirstLogin' => (bool) $this->resource->is_first_login,
                'preferences' => (object) ($this->resource->preferences ?? []),
            ];
        }

        return [
            'id' => 'anonymous',
            'name' => 'anonymous',
            'username' => 'anonymous',
            'email' => 'anonymous',
            'lang' => null,
            'groups' => [],
            'canImpersonate' => false,
            'permissions' => [],
            'isFirstLogin' => false,
            'preferences' => (object) [],
        ];
    }
}
