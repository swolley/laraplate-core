<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

/**
 * Runs an ordered chain of {@see Rule}s over an error message. Order matters:
 * stack traces and volatile payloads are removed before the finer substitutions
 * run over what remains. Extending the algorithm means adding a rule, never
 * editing a long method.
 */
final readonly class FingerprintNormalizer
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(private array $rules) {}

    public static function default(): self
    {
        return new self([
            new StripStackTraces,
            new CollapseVolatilePayloads,
            new CollapseSqlState,
            new SubstituteUuidIpHex,
            new SubstituteNumbersInValuePosition,
        ]);
    }

    public function normalize(string $message): string
    {
        foreach ($this->rules as $rule) {
            $message = $rule->apply($message);
        }

        return mb_trim($message);
    }
}
