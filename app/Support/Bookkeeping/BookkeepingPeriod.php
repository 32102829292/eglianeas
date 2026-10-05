<?php

namespace App\Support\Bookkeeping;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The calendar period a bookkeeping plan covers.
 *
 * Weekly Bookkeeping keys its plan off `week_start`; the monthly and quarterly
 * modules key theirs off `month_start` and `quarter_start` in exactly the same
 * way, so both new modules share one period abstraction instead of each
 * re-deriving "first day of the period" in half a dozen places.
 */
final class BookkeepingPeriod
{
    public const MONTHLY = 'monthly';

    public const QUARTERLY = 'quarterly';

    private function __construct(
        public readonly string $kind,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
    }

    public static function monthly(int $year, int $month): self
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }

        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();

        return new self(self::MONTHLY, $start, $start->endOfMonth()->startOfDay());
    }

    public static function quarterly(int $year, int $quarter): self
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('Quarter must be between 1 and 4.');
        }

        $startMonth = (($quarter - 1) * 3) + 1;
        $start = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();

        return new self(self::QUARTERLY, $start, $start->addMonths(2)->endOfMonth()->startOfDay());
    }

    public static function current(string $kind): self
    {
        return self::fromDate($kind, CarbonImmutable::now());
    }

    /**
     * Normalises any date into the period that contains it, so a caller can hand
     * us "2026-03-17" or "2026-03-01" and both land on March.
     */
    public static function fromDate(string $kind, CarbonInterface|string $date): self
    {
        if ($date instanceof CarbonInterface) {
            $parsed = CarbonImmutable::instance($date);
        } else {
            $raw = trim((string) $date);

            // Gate on strtotime() first: it answers "is this a date at all?"
            // without emitting the warning Carbon's parser raises on junk.
            if ($raw === '' || strtotime($raw) === false) {
                throw new InvalidArgumentException("Unreadable bookkeeping period [{$raw}].");
            }

            try {
                $parsed = CarbonImmutable::parse($raw);
            } catch (\Throwable $e) {
                throw new InvalidArgumentException("Unreadable bookkeeping period [{$raw}].", 0, $e);
            }
        }

        if ($kind === self::MONTHLY) {
            return self::monthly($parsed->year, $parsed->month);
        }

        if ($kind === self::QUARTERLY) {
            return self::quarterly($parsed->year, (int) ceil($parsed->month / 3));
        }

        throw new InvalidArgumentException("Unknown bookkeeping period [{$kind}].");
    }

    /**
     * Accepts the period's own key ("2026-03", "2026-Q1") as well as any plain
     * date inside it, so a URL parameter and a form field resolve identically.
     *
     * The result always belongs to `$kind`: a "2026-04" key asked for by the
     * quarterly module resolves to the quarter containing April, never to a
     * month-long period.
     */
    public static function fromKey(string $kind, string $key): self
    {
        $key = trim($key);

        if ($key !== '' && preg_match('/^(\d{4})[-\s]?Q([1-4])$/i', $key, $matches) === 1) {
            $quarter = self::quarterly((int) $matches[1], (int) $matches[2]);

            // A quarterly key never widens a monthly plan: the monthly module
            // reads it as the first month of that quarter rather than storing
            // three months of range in month_start / month_end.
            return $kind === self::QUARTERLY
                ? $quarter
                : self::monthly($quarter->start->year, $quarter->start->month);
        }

        if ($key !== '' && preg_match('/^(\d{4})-(\d{1,2})$/', $key, $matches) === 1) {
            return self::fromDate($kind, CarbonImmutable::create((int) $matches[1], (int) $matches[2], 1));
        }

        return self::fromDate($kind, $key);
    }

    public function isMonthly(): bool
    {
        return $this->kind === self::MONTHLY;
    }

    public function isQuarterly(): bool
    {
        return $this->kind === self::QUARTERLY;
    }

    /**
     * The value used in URLs, selects and the query string. The month form
     * doubles as the period's start date, which keeps one value doing both jobs.
     */
    public function key(): string
    {
        if ($this->isMonthly()) {
            return $this->start->format('Y-m');
        }

        return sprintf('%d-Q%d', $this->start->year, $this->quarterNumber());
    }

    public function startDate(): string
    {
        return $this->start->format('Y-m-d');
    }

    public function endDate(): string
    {
        return $this->end->format('Y-m-d');
    }

    public function quarterNumber(): int
    {
        return (int) ceil($this->start->month / 3);
    }

    /** Human label for a single period, e.g. "March 2026" or "Q1 2026". */
    public function label(): string
    {
        return $this->isMonthly()
            ? $this->start->format('F Y')
            : sprintf('Q%d %d', $this->quarterNumber(), $this->start->year);
    }

    /** Compact label for selects and pills, e.g. "Mar 2026" or "Q1 2026". */
    public function shortLabel(): string
    {
        return $this->isMonthly()
            ? $this->start->format('M Y')
            : $this->label();
    }

    /** Full span, e.g. "Mar 1, 2026 – Mar 31, 2026". */
    public function rangeLabel(): string
    {
        return $this->start->format('M j, Y').' – '.$this->end->format('M j, Y');
    }

    public function shortRangeLabel(): string
    {
        return $this->start->format('M j').' – '.$this->end->format('M j, Y');
    }

    /** Query-string key the period travels under, e.g. "month" / "quarter". */
    public function queryName(): string
    {
        return $this->isMonthly() ? 'month' : 'quarter';
    }

    public function previous(): self
    {
        return $this->shifted(-1);
    }

    public function next(): self
    {
        return $this->shifted(1);
    }

    private function shifted(int $steps): self
    {
        return self::fromDate(
            $this->kind,
            $this->start->addMonthsNoOverflow($steps * ($this->isMonthly() ? 1 : 3))
        );
    }

    public function isCurrent(): bool
    {
        $now = CarbonImmutable::now();

        if ($this->start->year !== $now->year) {
            return false;
        }

        return $this->isMonthly()
            ? $this->start->month === $now->month
            : $this->quarterNumber() === (int) ceil($now->month / 3);
    }

    public function contains(CarbonInterface|string $date): bool
    {
        $date = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->startOfDay()
            : CarbonImmutable::parse($date)->startOfDay();

        return $date->betweenIncluded($this->start, $this->end);
    }

    /**
     * Select options around the active period, oldest first, so a planner can
     * look back at closed periods and forward into periods not planned yet.
     *
     * @return array<string, string> key => label
     */
    public function options(int $back = 12, int $forward = 6): array
    {
        $options = [];
        $cursor = $back > 0 ? $this->shifted(-$back) : $this;

        for ($i = 0; $i <= $back + $forward; $i++) {
            $options[$cursor->key()] = $cursor->label();
            $cursor = $cursor->next();
        }

        return $options;
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'key' => $this->key(),
            'start' => $this->startDate(),
            'end' => $this->endDate(),
            'label' => $this->label(),
            'short_label' => $this->shortLabel(),
            'range_label' => $this->rangeLabel(),
        ];
    }
}
