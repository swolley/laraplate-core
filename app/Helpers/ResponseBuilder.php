<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use UnexpectedValueException;

class ResponseBuilder
{
    private int $status = Response::HTTP_OK;

    private mixed $error = null;

    private ?int $totalRecords = null;

    private ?int $currentRecords = null;

    private ?int $currentPage = null;

    private ?int $totalPages = null;

    private ?int $pagination = null;

    private ?int $from = null;

    private ?int $to = null;

    private ?string $class = null;

    private ?string $table = null;

    private bool $preview = false;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    private ?Carbon $cachedAt = null;

    private ResourceCollection|JsonResource|null $resourceResponse = null;

    public function __construct(private ?Request $request)
    {
        $this->preview = preview();
    }

    public function getHttpErrorStatus(int|string $errorCode): int
    {
        $http_statuses = array_flip(self::getHttpStatuses());

        return $errorCode !== 0 && is_int($errorCode) && isset($http_statuses[$errorCode]) ? $errorCode : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getResourceResponse(): ResourceCollection|JsonResource|null
    {
        return $this->resourceResponse ?? null;
    }

    /**
     * Set the value of data.
     */
    public function setData(mixed $data): self
    {
        if ($data instanceof JsonResource) {
            $this->resourceResponse = $data;
            $realData = $this->resourceResponse->resource;

            if ($realData && ($realData instanceof Collection || (is_array($realData) && Arr::isList($realData)))) {
                $this->setClass($data instanceof Collection ? $data->first() : $data[0]);
            } elseif (is_object($data)) {
                $this->setClass($data);
            }
        } elseif ($data instanceof Collection || (is_array($data) && Arr::isList($data))) {
            $this->resourceResponse = new ResourceCollection($data);

            if (count($data) > 0) {
                $this->setClass($data instanceof Collection ? $data->first() : $data[0]);
            }
        } elseif ($data instanceof Throwable) {
            report($data);
            $this->resourceResponse = new JsonResource(null);
            $this->setError($data);
            $this->setClass($data);
            $this->setStatus(self::getHttpErrorStatus($data->getCode()));
        } else {
            $this->resourceResponse = new JsonResource($data);

            if (is_object($data)) {
                $this->setClass($data);
            }
        }

        return $this;
    }

    public function isOk(): bool
    {
        return ! $this->error && $this->status < 400;
    }

    public function isError(): bool
    {
        return ! $this->isOk();
    }

    public function isEmpty(): bool
    {
        return ! $this->resourceResponse instanceof JsonResource || $this->resourceResponse->resource === null;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Set the value of status.
     */
    public function setStatus(int $status): self
    {
        throw_unless(in_array($status, self::getHttpStatuses(), true), UnexpectedValueException::class, $status . ' is not a valid status');
        $this->status = $status;

        return $this;
    }

    public function getError(): mixed
    {
        return $this->error;
    }

    /**
     * Set the value of error.
     *
     * @param  string|array<int,string>|Throwable|null  $error
     */
    public function setError(string|array|Throwable|null $error): self
    {
        $this->error = $error;

        if (! $this->error) {
            $this->setStatus(Response::HTTP_OK);
        } elseif ($this->error instanceof Throwable) {
            $this->setStatus(self::getHttpErrorStatus($this->error->getCode()));
        } elseif (is_array($this->error)) {
            $this->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        } else {
            $this->setStatus(self::getHttpErrorStatus($this->error));
        }

        return $this;
    }

    public function getTotalRecords(): ?int
    {
        return $this->totalRecords;
    }

    /**
     * Set the value of totalRecords.
     */
    public function setTotalRecords(?int $totalRecords): self
    {
        $this->totalRecords = $totalRecords;

        return $this;
    }

    public function getCurrentRecords(): ?int
    {
        return $this->currentRecords;
    }

    /**
     * Set the value of pageRecords.
     */
    public function setCurrentRecords(?int $currentRecords): self
    {
        $this->currentRecords = $currentRecords;

        return $this;
    }

    public function getCurrentPage(): ?int
    {
        return $this->currentPage;
    }

    /**
     * Set the value of currentPage.
     */
    public function setCurrentPage(?int $currentPage): self
    {
        $this->currentPage = $currentPage;

        return $this;
    }

    public function getTotalPages(): ?int
    {
        return $this->totalPages;
    }

    /**
     * Set the value of totalPages.
     */
    public function setTotalPages(?int $totalPages): self
    {
        $this->totalPages = $totalPages;

        return $this;
    }

    public function getPagination(): ?int
    {
        return $this->pagination;
    }

    /**
     * Set the value of pagination.
     */
    public function setPagination(?int $pagination): self
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function getFrom(): ?int
    {
        return $this->from;
    }

    /**
     * Set the value of from.
     */
    public function setFrom(?int $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getTo(): ?int
    {
        return $this->to;
    }

    /**
     * Set the value of to.
     */
    public function setTo(?int $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * Set the value of class.
     *
     * @param  object|class-string|null  $class
     */
    public function setClass(object|string|null $class): self
    {
        $this->class = is_object($class) ? $class::class : $class;

        if ($class instanceof Model) {
            $this->table = $class->getTable();
        }

        return $this;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Set the value of table.
     */
    public function setTable(?string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the value of headers.
     *
     * @param  array<string, string>  $headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function setHeader(string $header, ?string $value): self
    {
        if ($value !== null) {
            $this->headers[$header] = $value;
        } else {
            unset($this->headers[$header]);
        }

        return $this;
    }

    public function getCachedAt(): ?Carbon
    {
        return $this->cachedAt;
    }

    /**
     * Set the value of cachedAt.
     */
    public function setCachedAt(?Carbon $cachedAt = null): self
    {
        $this->cachedAt = $cachedAt ?? new Carbon();

        return $this;
    }

    public function getPreview(): bool
    {
        return $this->preview;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function data(): mixed
    {
        return $this->resourceResponse->resource;
    }

    public function getResponse(): JsonResponse
    {
        $data = $this->getResponseData();

        $response = $data['payload']->toResponse($this->request);
        $response->setStatusCode($data['statusCode']);

        foreach ($data['headers'] as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    public function json(): JsonResponse
    {
        return $this->getResponse();
    }

    public function serialize(): string
    {
        // Request object is not serializable
        // Temporarily unset the request object before serialization
        $request = $this->request;
        $this->request = null;

        $serialized = serialize($this->getResponseData());

        // Restore the request object
        $this->request = $request;

        return $serialized;
    }

    public function unserialize(string $serializedResponse): JsonResponse
    {
        $responseProperties = unserialize($serializedResponse);

        return $this->buildResponse($responseProperties);
    }

    /**
     * @return array<int|string>
     *
     * @psalm-return array<array-key>
     */
    private function getHttpStatuses(): array
    {
        $https_statuses = Response::$statusTexts;
        $https_statuses[419] = 'Session expired';

        return array_flip($https_statuses);
    }

    private function getResponseData(): array
    {
        $payload = [
            'meta' => [
                'status' => $this->status,
                'preview' => $this->preview,
            ],
        ];

        if ($this->error !== null) {
            if ($this->error instanceof Exception) {
                $payload['error'] = $this->error->getMessage();

                if (config('app.debug') === true) {
                    $payload['exception'] = [
                        'code' => $this->error->getCode(),
                        'file' => $this->error->getFile(),
                        'line' => $this->error->getLine(),
                        'trace' => $this->error->getTrace(),
                    ];
                }
            } else {
                $payload['error'] = $this->error;
            }
        }

        if (! $this->resourceResponse instanceof JsonResource) {
            $this->resourceResponse = new JsonResource(null);
        } elseif ($this->resourceResponse instanceof ResourceCollection) {
            if ($this->totalRecords !== null) {
                $payload['meta']['totalRecords'] = $this->totalRecords;
            }

            if ($this->currentRecords !== null) {
                $payload['meta']['currentRecords'] = $this->currentRecords;
            }

            if ($this->currentPage !== null) {
                $payload['meta']['currentPage'] = $this->currentPage;
            }

            if ($this->totalPages !== null) {
                $payload['meta']['totalPages'] = $this->totalPages;
            }

            if ($this->pagination !== null) {
                $payload['meta']['pagination'] = $this->pagination;
            }

            if ($this->from !== null) {
                $payload['meta']['from'] = $this->from;

                if ($this->to !== null) {
                    $count = $this->resourceResponse->resource->count();
                    $payload['meta']['to'] = $count < $this->to - $this->from + 1 ? $this->from + $count - 1 : $this->to;
                }
            }
        }

        if ($this->cachedAt instanceof Carbon) {
            $payload['meta']['cachedAt'] = $this->cachedAt;
        }

        if (config('app.debug')) {
            if ($this->class !== null) {
                $payload['meta']['class'] = $this->class;
            }

            if ($this->table !== null) {
                $payload['meta']['table'] = $this->table;
            }

            $route = request()->route();
            $payload['meta']['controller'] = $route->getControllerClass();
            $payload['meta']['action'] = $route->getActionMethod();
        }

        $this->resourceResponse->with = $payload;

        return [
            'payload' => $this->resourceResponse,
            'statusCode' => $this->status,
            'headers' => $this->headers,
        ];
    }

    private function buildResponse(array $responseProperties): JsonResponse
    {
        return new JsonResponse($responseProperties['content'], $responseProperties['statusCode'], $responseProperties['headers']);
    }
}
