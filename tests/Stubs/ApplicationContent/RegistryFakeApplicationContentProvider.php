<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\ApplicationContent;

use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;

final readonly class RegistryFakeApplicationContentProvider implements ApplicationContentRetrievalProviderInterface
{
    public function __construct(private ApplicationContentSourceDescriptor $source) {}

    public function descriptor(): ApplicationContentSourceDescriptor
    {
        return $this->source;
    }

    public function retrieve(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): ApplicationContentResult {
        return new ApplicationContentResult($query->source, [], 'lexical', false);
    }
}
