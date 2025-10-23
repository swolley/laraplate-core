<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Crud\CrudHelper;
use Modules\Core\Models\ACL;

final readonly class AclService
{
    public function __construct(private CrudHelper $crudHelper) {}

    public function applyAclToQuery(Builder $query, int $permission_id): Builder
    {
        $acls = ACL::forPermission($permission_id)->get();

        return $query->where(function (Builder $mainQuery) use ($acls, $query): void {
            foreach ($acls as $acl) {
                // Creiamo un ListRequestData simulato con i dati dell'ACL
                $requestData = new ListRequestData(
                    request(),           // Current request
                    $query->getModel()->getTable(),  // Entity
                    [
                        'filters' => $acl->filters ? (array) $acl->filters : [],
                        'sort' => $acl->sort ? (array) $acl->sort : [],
                    ],
                    $query->getModel()->getKeyName(),
                );

                // Utilizziamo direttamente il CrudHelper
                $this->crudHelper->prepareQuery($mainQuery, $requestData);
            }
        });
    }
}
