<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\ACL;
use Modules\Core\Crud\CrudHelper;
use Modules\Core\Casts\ListRequestData;

class AclService
{
    private CrudHelper $crudHelper;

    public function __construct(CrudHelper $crudHelper)
    {
        $this->crudHelper = $crudHelper;
    }

    public function applyAclToQuery(Builder $query, int $permission_id): Builder
    {
        $acls = ACL::forPermission($permission_id)->get();

        return $query->where(function ($mainQuery) use ($acls, $query) {
            foreach ($acls as $acl) {
                // Creiamo un ListRequestData simulato con i dati dell'ACL
                $requestData = new ListRequestData(
                    request(),           // Current request
                    $query->getModel()->getTable(),  // Entity
                    [
                        'filters' => $acl->filters->toArray(),
                        'sort' => $acl->sort?->toArray(),
                    ],
                    $query->getModel()->getKeyName()
                );

                // Utilizziamo direttamente il CrudHelper
                $this->crudHelper->prepareQuery($mainQuery, $requestData);
            }
        });
    }
}
