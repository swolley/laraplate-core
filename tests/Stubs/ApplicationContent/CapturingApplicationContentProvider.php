<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\ApplicationContent;

use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentHit;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Throwable;

final class CapturingApplicationContentProvider implements ApplicationContentRetrievalProviderInterface
{
    public int $calls = 0;

    public ?ApplicationContentAuthorization $capturedAuthorization = null;

    public ?Throwable $failure = null;

    public ?ApplicationContentResult $result = null;

    public function __construct(public ApplicationContentSourceDescriptor $source) {}

    public function descriptor(): ApplicationContentSourceDescriptor
    {
        return $this->source;
    }

    public function retrieve(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): ApplicationContentResult {
        $this->calls++;
        $this->capturedAuthorization = $authorization;

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->result ?? new ApplicationContentResult(
            $query->source,
            [self::defaultHit()],
            'lexical',
            false,
        );
    }

    public static function defaultHit(int $key = 1): ApplicationContentHit
    {
        return new ApplicationContentHit(
            'core-user-'.$key,
            'core.users',
            'core',
            'users',
            $key,
            'Visible application information.',
            'Visible record',
            '/app/core/users/'.$key,
            'en',
            'lexical',
            0.8,
            null,
            false,
        );
    }
}
