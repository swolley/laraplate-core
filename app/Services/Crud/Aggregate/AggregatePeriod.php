<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Carbon\CarbonImmutable;
use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Enums\TimeBucket;
use Modules\Core\Exceptions\AggregateException;

/**
 * The period of an aggregate, resolved to two days: `from` and `to` are the start of their day in the
 * application time zone, and `to` is inclusive.
 *
 * A client says the period in one of three ways, which {@see self::resolve()} turns into the same value:
 * `last: {n, unit}` ends today and starts at the beginning of the unit `n - 1` units before the current one,
 * `preset` is one of a closed list (a `this_*` preset is the whole unit, the days to come included), and
 * `from`/`to` are two dates, `Y-m-d`. The dates are days of the application time zone, which is what the
 * stored values are in, so no conversion is done.
 */
final readonly class AggregatePeriod
{
    /**
     * The greatest count of `last`. A larger one is not a period anyone asks for, and it takes the date
     * arithmetic out of the range it computes in: the engine's own `max_period_days` is what limits a period.
     */
    private const int MAX_UNITS = 100000;

    /**
     * Each preset as the bucket it is and how many buckets before the current one.
     */
    private const array PRESETS = [
        'today' => [TimeBucket::Day, 0],
        'yesterday' => [TimeBucket::Day, 1],
        'this_week' => [TimeBucket::Week, 0],
        'last_week' => [TimeBucket::Week, 1],
        'this_month' => [TimeBucket::Month, 0],
        'last_month' => [TimeBucket::Month, 1],
        'this_quarter' => [TimeBucket::Quarter, 0],
        'last_quarter' => [TimeBucket::Quarter, 1],
        'this_year' => [TimeBucket::Year, 0],
        'last_year' => [TimeBucket::Year, 1],
    ];

    public function __construct(
        public string $field,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /**
     * Resolves what a request says against the day it is made.
     *
     * @param  array<string, mixed>  $spec  `field`, and exactly one of `last` (`{n, unit}`), `preset` or `from` with `to`.
     * @param  CarbonImmutable  $now  The moment of the request: its day, in the application time zone, is today.
     *
     * @throws AggregateException With `INVALID_FIELD` on `period` when the spec is malformed.
     */
    public static function resolve(array $spec, CarbonImmutable $now): self
    {
        $field = $spec['field'] ?? null;

        if (! is_string($field) || $field === '') {
            throw self::invalid('A period needs the field it is measured on.');
        }

        $hasLast = isset($spec['last']);
        $hasPreset = isset($spec['preset']);
        $hasRange = isset($spec['from']) || isset($spec['to']);

        if ((int) $hasLast + (int) $hasPreset + (int) $hasRange !== 1) {
            throw self::invalid('A period is given by exactly one of "last", "preset" or "from" with "to".');
        }

        $today = $now->setTimezone(self::timezone())->startOfDay();

        [$from, $to] = match (true) {
            $hasLast => self::last($spec['last'], $today),
            $hasPreset => self::preset($spec['preset'], $today),
            default => self::range($spec['from'] ?? null, $spec['to'] ?? null),
        };

        return new self($field, $from, $to);
    }

    /**
     * The number of days of the period, both ends counted.
     */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * The period of the same length immediately before this one.
     *
     * A period that starts on the first day of a month and ends on the last day of a month shifts by that many
     * calendar months, so `this_quarter` gives the quarter before it. Any other period shifts by its own length
     * in days, so a partial `last 12 months` gives the days before it, not twelve months that were never asked.
     */
    public function previous(): self
    {
        $end = $this->from->subDay();

        if ($this->coversWholeMonths()) {
            $months = ($this->to->year - $this->from->year) * 12 + $this->to->month - $this->from->month + 1;

            return new self($this->field, $this->from->subMonths($months), $end);
        }

        return new self($this->field, $this->from->subDays($this->days()), $end);
    }

    /**
     * The first day of every bucket from the one that holds `from` to the one that holds `to`, so the first
     * and the last may start outside the period.
     *
     * @return list<CarbonImmutable>
     */
    public function buckets(TimeBucket $bucket): array
    {
        $last = $bucket->start($this->to);
        $starts = [];

        for ($start = $bucket->start($this->from); $start <= $last; $start = $bucket->next($start)) {
            $starts[] = $start;
        }

        return $starts;
    }

    /**
     * What a result says of its period: the resolved dates as `Y-m-d` and the time zone they are days of.
     *
     * @return array{field: string, from: string, to: string, timezone: string}
     */
    public function toMeta(): array
    {
        return [
            'field' => $this->field,
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
            'timezone' => self::timezone(),
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function last(mixed $last, CarbonImmutable $today): array
    {
        if (! is_array($last) || ! isset($last['n'], $last['unit']) || ! is_int($last['n']) || ! is_string($last['unit'])) {
            throw self::invalid('The "last" of a period is a count "n" and a "unit".');
        }

        if ($last['n'] < 1 || $last['n'] > self::MAX_UNITS) {
            throw self::invalid(sprintf('The count of "last" is a whole number from 1 to %d.', self::MAX_UNITS));
        }

        $unit = TimeBucket::fromInput($last['unit']);

        if (! $unit instanceof TimeBucket) {
            throw self::invalid('The unit of "last" is one of ' . self::bucketNames() . '.');
        }

        return [self::bucketStart($unit, $today, $last['n'] - 1), $today];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function preset(mixed $preset, CarbonImmutable $today): array
    {
        if (! is_string($preset) || ! isset(self::PRESETS[$preset])) {
            throw self::invalid('The preset of a period is one of ' . implode(', ', array_keys(self::PRESETS)) . '.');
        }

        [$unit, $back] = self::PRESETS[$preset];
        $from = self::bucketStart($unit, $today, $back);

        return [$from, $unit->next($from)->subDay()];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function range(mixed $from, mixed $to): array
    {
        $first = self::date($from, 'from');
        $last = self::date($to, 'to');

        if ($first > $last) {
            throw self::invalid('The "from" of a period is not after its "to".');
        }

        return [$first, $last];
    }

    private static function date(mixed $value, string $name): CarbonImmutable
    {
        $parts = [];

        if (
            ! is_string($value)
            || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            throw self::invalid(sprintf('The "%s" of a period is a date, as YYYY-MM-DD.', $name));
        }

        return CarbonImmutable::createFromDate((int) $parts[1], (int) $parts[2], (int) $parts[3], self::timezone())->startOfDay();
    }

    /**
     * The first day of the bucket that is `$back` buckets before the one that holds the day.
     */
    private static function bucketStart(TimeBucket $unit, CarbonImmutable $day, int $back): CarbonImmutable
    {
        $start = $unit->start($day);

        return match ($unit) {
            TimeBucket::Day => $start->subDays($back),
            TimeBucket::Week => $start->subWeeks($back),
            TimeBucket::Month => $start->subMonths($back),
            TimeBucket::Quarter => $start->subQuarters($back),
            TimeBucket::Year => $start->subYears($back),
        };
    }

    private static function bucketNames(): string
    {
        return implode(', ', array_map(static fn (TimeBucket $bucket): string => $bucket->value, TimeBucket::cases()));
    }

    private static function timezone(): string
    {
        return config()->string('app.timezone');
    }

    private static function invalid(string $message): AggregateException
    {
        return new AggregateException(AggregateErrorCode::InvalidField, $message, 'period');
    }

    private function coversWholeMonths(): bool
    {
        return $this->from->day === 1 && $this->to->day === $this->to->daysInMonth;
    }
}
