<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Stands in for the PSR-18 network exceptions the HTTP clients raise when a
 * search engine host refuses the connection.
 */
final class UnreachableNetworkException extends RuntimeException implements NetworkExceptionInterface
{
    private readonly RequestInterface $request;

    public function __construct(string $message = 'Failed to connect to 127.0.0.1:8108')
    {
        parent::__construct($message);

        $this->request = new Request('GET', 'http://127.0.0.1:8108/collections');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
