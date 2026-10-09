<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

/**
 * A Scout-like builder that records the constraints the engine would receive.
 */
final class RecordingEngineBuilderStub
{
    /**
     * @var list<array{method: string, field: string, value: mixed}>
     */
    public array $calls = [];

    /**
     * @var array<string, mixed>
     */
    public array $options = [];

    public function where(string $field, mixed $value): self
    {
        $this->calls[] = ['method' => 'where', 'field' => $field, 'value' => $value];

        return $this;
    }

    /**
     * @param  array<int, mixed>  $value
     */
    public function whereIn(string $field, array $value): self
    {
        $this->calls[] = ['method' => 'whereIn', 'field' => $field, 'value' => $value];

        return $this;
    }
}
