<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use InvalidArgumentException;

/**
 * A condition named by a local scope of the filtered model, e.g. `{"scope": "valid"}`.
 *
 * The scope must be declared with {@see \Modules\Core\Attributes\FilterScope}; its parameters are positional,
 * after the query builder, and are checked against the method signature by
 * {@see \Modules\Core\Support\ScopeGuard} before the scope runs. A scope node only adds conditions: it never
 * removes a global scope and is never negated.
 */
final readonly class ScopeFilter
{
    /**
     * The scope arguments after the builder, in signature order.
     *
     * @var list<mixed>
     */
    public array $parameters;

    /**
     * @param  array<mixed>  $parameters  The scope arguments after the builder; must be a list, as they are positional
     */
    public function __construct(
        public string $scope,
        array $parameters = [],
    ) {
        if (! array_is_list($parameters)) {
            throw new InvalidArgumentException(sprintf('The parameters of scope filter "%s" must be a list.', $scope));
        }

        $this->parameters = $parameters;
    }

    /**
     * @return array{scope: string, parameters: list<mixed>}
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'parameters' => $this->parameters,
        ];
    }
}
