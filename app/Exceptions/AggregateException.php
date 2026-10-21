<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Enums\AggregateErrorCode;
use Override;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An aggregate request the engine refuses: it carries the contract code, a message for the client and, when the
 * refusal is about one part of the request, the path of that part. The message never holds SQL text.
 *
 * It answers for itself with a 422: the request validates when the framework resolves it, before the action runs,
 * and only the exception can answer from there. A refusal is the client's mistake and is not reported, unless it
 * is built `reportable`: a fault of the deployment, such as a database driver with no day expression (or a timeout,
 * whose code is reserved), which the log must show.
 */
final class AggregateException extends RuntimeException implements Responsable
{
    public function __construct(
        public readonly AggregateErrorCode $errorCode,
        string $message,
        public readonly ?string $field = null,
        public readonly bool $reportable = false,
    ) {
        parent::__construct($message);
    }

    /**
     * Whether the exception handler is done with the refusal: a client's mistake needs nothing more, and a
     * reportable refusal goes on to the log (the handler reports an exception whose `report()` returns false).
     */
    public function report(): bool
    {
        return ! $this->reportable;
    }

    /**
     * @return array{code: string, message: string, field?: string}
     */
    public function toArray(): array
    {
        $error = [
            'code' => $this->errorCode->value,
            'message' => $this->getMessage(),
        ];

        if ($this->field !== null) {
            $error['field'] = $this->field;
        }

        return $error;
    }

    /**
     * The refusal as the route answers it: `{error: {code, message, field?}}`.
     *
     * @param  Request  $request
     */
    #[Override]
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['error' => $this->toArray()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
