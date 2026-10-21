<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
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
 * It is the client's mistake, never a fault of the server, so it answers for itself with a 422 and is not reported:
 * the request validates when the framework resolves it, before the action runs, and only the exception can answer
 * from there.
 */
final class AggregateException extends RuntimeException implements Responsable, ShouldntReport
{
    public function __construct(
        public readonly AggregateErrorCode $errorCode,
        string $message,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message);
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
