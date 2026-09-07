<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\ApplicationContent;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Contracts\ProvidesPermissionModel;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;

final class PermissionModelFakeProvider implements ApplicationContentRetrievalProviderInterface, ProvidesPermissionModel
{
    public int $calls = 0;

    public ?ApplicationContentAuthorization $capturedAuthorization = null;

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        public ApplicationContentSourceDescriptor $source,
        private string $model,
    ) {}

    public function descriptor(): ApplicationContentSourceDescriptor
    {
        return $this->source;
    }

    public function permissionModel(): string
    {
        return $this->model;
    }

    public function retrieve(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): ApplicationContentResult {
        $this->calls++;
        $this->capturedAuthorization = $authorization;

        return new ApplicationContentResult($query->source, [], 'lexical', false);
    }
}
