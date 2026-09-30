<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Illuminate\Contracts\Config\Repository;
use Modules\Core\Search\Enums\QueryClass;
use Psr\Log\LoggerInterface;

/**
 * Merges the committed retrieval tuning profile (`config/search_tuning.php`) into a search plan.
 *
 * Runs only when the `search.adaptive_tuning` setting (`core.search.adaptive_tuning`) is on.
 * It shapes fusion (`plan.ensemble`) and reranking (`plan.ranking`) and never strategy
 * selection (`plan.retrieval`), which stays a capability question. When the switch is off, or
 * the profile is missing or invalid, the plan is returned untouched (L0); an unusable profile
 * is reported once per instance.
 */
final readonly class RetrievalTuningProfile
{
    public const string SETTING_KEY = 'core.search.adaptive_tuning';

    public const string PROFILE_KEY = 'search_tuning';

    /**
     * @var array<string, 'ensemble'|'ranking'>
     */
    private const array PARAMETER_SECTIONS = [
        'keyword_weight' => 'ensemble',
        'vector_weight' => 'ensemble',
        'hybrid_weight' => 'ensemble',
        'rrf_k' => 'ensemble',
        'rrf_weight' => 'ensemble',
        'agreement_boost' => 'ensemble',
        'rerank_top_k' => 'ranking',
        'rerank_blend' => 'ranking',
    ];

    /**
     * @var list<string>
     */
    private const array POSITIVE_INTEGER_PARAMETERS = ['rrf_k', 'rerank_top_k'];

    /**
     * @var list<string>
     */
    private const array WEIGHT_PARAMETERS = ['keyword_weight', 'vector_weight', 'hybrid_weight'];

    public function __construct(
        private Repository $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * Why a parameter set is invalid, or null when it is valid: every name must be a known
     * parameter, weights, `rrf_weight`, `agreement_boost` and `rerank_blend` must be numbers in
     * [0, 1], `rrf_k` and `rerank_top_k` integers >= 1, and a set defining all three strategy
     * weights must keep one of them positive.
     *
     * @param  array<mixed>  $parameters
     */
    public static function invalidParameters(string $entry, array $parameters): ?string
    {
        foreach ($parameters as $name => $value) {
            if (! is_string($name) || ! array_key_exists($name, self::PARAMETER_SECTIONS)) {
                return "[{$entry}] has unknown parameter [{$name}]";
            }

            if (in_array($name, self::POSITIVE_INTEGER_PARAMETERS, true)) {
                if (! is_int($value) || $value < 1) {
                    return "[{$entry}.{$name}] must be an integer >= 1";
                }

                continue;
            }

            if ((! is_int($value) && ! is_float($value)) || $value < 0 || $value > 1) {
                return "[{$entry}.{$name}] must be a number in [0, 1]";
            }
        }

        if (! self::hasPositiveWeight($parameters)) {
            return "[{$entry}] sets every strategy weight to zero";
        }

        return null;
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get(self::SETTING_KEY, false);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function apply(array $plan, QueryClass $class): array
    {
        if (! $this->enabled()) {
            return $plan;
        }

        $profile = $this->profile();

        if ($profile === null) {
            return $plan;
        }

        $parameters = $this->parametersFor($class);
        $ensemble = is_array($plan['ensemble'] ?? null) ? $plan['ensemble'] : [];
        $ranking = is_array($plan['ranking'] ?? null) ? $plan['ranking'] : [];

        foreach ($parameters as $name => $value) {
            if (self::PARAMETER_SECTIONS[$name] === 'ensemble') {
                $ensemble[$name] = $value;
            } else {
                $ranking[$name] = $value;
            }
        }

        $plan['ensemble'] = $ensemble;
        $plan['ranking'] = $ranking;
        $meta = is_array($plan['meta'] ?? null) ? $plan['meta'] : [];
        $meta['tuning'] = [
            'applied' => true,
            'profile_version' => $profile['version'],
            'query_class' => $class->value,
        ];
        $plan['meta'] = $meta;

        return $plan;
    }

    /**
     * The validated profile, or null when it is missing or invalid (reported once per instance).
     *
     * @return array{version: string, default: array<string, int|float>, classes: array<string, array<string, int|float>>}|null
     */
    public function profile(): ?array
    {
        return once(function (): ?array {
            $raw = $this->config->get(self::PROFILE_KEY);
            $error = $this->validationError($raw);

            if ($error !== null) {
                $this->logger->warning('Retrieval tuning profile unusable; tuning disabled', ['reason' => $error]);

                return null;
            }

            /** @var array{version: string, default: array<string, int|float>, classes: array<string, array<string, int|float>>} $raw */
            return $raw;
        });
    }

    /**
     * The merged `default` + class parameter set of the valid profile, or an empty set when the
     * profile is missing or invalid. Parameters it leaves out keep the planner values.
     *
     * @return array<string, int|float>
     */
    public function parametersFor(QueryClass $class): array
    {
        $profile = $this->profile();

        if ($profile === null) {
            return [];
        }

        return [...$profile['default'], ...($profile['classes'][$class->value] ?? [])];
    }

    /**
     * Whether a parameter set leaves at least one strategy weight positive. A set that does not
     * define all three weights passes: the missing ones keep the planner values.
     *
     * @param  array<mixed>  $parameters
     */
    private static function hasPositiveWeight(array $parameters): bool
    {
        $weights = array_intersect_key($parameters, array_flip(self::WEIGHT_PARAMETERS));

        if (count($weights) < count(self::WEIGHT_PARAMETERS)) {
            return true;
        }

        return array_any($weights, static fn (mixed $weight): bool => is_numeric($weight) && (float) $weight > 0.0);
    }

    private function validationError(mixed $raw): ?string
    {
        if (! is_array($raw)) {
            return 'profile config is missing';
        }

        if (! is_string($raw['version'] ?? null) || mb_trim($raw['version']) === '') {
            return 'profile version is missing';
        }

        if (! is_array($raw['default'] ?? null) || ! is_array($raw['classes'] ?? null)) {
            return 'profile must define default and classes arrays';
        }

        $error = self::invalidParameters('default', $raw['default']);

        if ($error !== null) {
            return $error;
        }

        foreach ($raw['classes'] as $class => $parameters) {
            if (! is_string($class) || QueryClass::tryFrom($class) === null) {
                return 'unknown query class [' . $class . ']';
            }

            if (! is_array($parameters)) {
                return "class [{$class}] must be an array";
            }

            $error = self::invalidParameters($class, [...$raw['default'], ...$parameters]);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }
}
