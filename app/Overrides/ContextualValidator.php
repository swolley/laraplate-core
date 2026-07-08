<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Validation\Validator;

final class ContextualValidator extends Validator
{
    /**
     * Optional metadata attached to validation failures for logging.
     *
     * @var array<string, mixed>
     */
    protected array $log_context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function withLogContext(array $context): static
    {
        $this->log_context = array_filter(
            $context,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogContext(): array
    {
        return $this->log_context;
    }
}
