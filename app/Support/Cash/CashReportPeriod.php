<?php

namespace App\Support\Cash;

use App\Support\Bookkeeping\BookkeepingPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Resolves a Cash Daily Position selection (client + period type + period +
 * year) into a concrete, inclusive date range.
 *
 * Month and quarter deliberately delegate to BookkeepingPeriod, the accounting
 * period convention the rest of the application already uses, so the report can
 * never drift from the bookkeeping modules. Week is the one exception: the
 * report groups a week WITHIN its month (1st–5th), which no existing helper
 * expresses, so that rule lives here and only here.
 */
final class CashReportPeriod
{
    public const WEEK = 'week';

    public const MONTH = 'month';

    public const QUARTER = 'quarter';

    public const TYPES = [
        self::WEEK => 'Week',
        self::MONTH => 'Month',
        self::QUARTER => 'Quarter',
    ];

    /** Only 29-, 30- and 31-day months can offer a fifth week. */
    public const FIFTH_WEEK_MIN_DAYS = 29;

    private function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly int $year,
        public readonly ?int $month = null,
        public readonly ?int $week = null,
        public readonly ?int $quarter = null,
    ) {
    }

    public static function week(int $year, int $month, int $week): self
    {
        self::assertYear($year);
        self::assertMonth($month);
        self::assertWeek($month, $week, $year);

        $firstOfMonth = CarbonImmutable::create($year, $month, 1)->startOfDay();

        $start = $firstOfMonth->addDays(($week - 1) * 7)->startOfDay();
        $end = $start->addDays(6)->endOfDay();

        // The last week never spills into the next month.
        $monthEnd = $firstOfMonth->endOfMonth()->endOfDay();
        if ($end->greaterThan($monthEnd)) {
            $end = $monthEnd;
        }

        return new self(self::WEEK, $start, $end, $year, $month, $week);
    }

    public static function month(int $year, int $month): self
    {
        self::assertYear($year);
        $period = BookkeepingPeriod::monthly($year, $month);

        return new self(
            self::MONTH,
            CarbonImmutable::parse($period->startDate())->startOfDay(),
            CarbonImmutable::parse($period->endDate())->endOfDay(),
            $year,
            $month,
        );
    }

    public static function quarter(int $year, int $quarter): self
    {
        self::assertYear($year);
        $period = BookkeepingPeriod::quarterly($year, $quarter);

        return new self(
            self::QUARTER,
            CarbonImmutable::parse($period->startDate())->startOfDay(),
            CarbonImmutable::parse($period->endDate())->endOfDay(),
            $year,
            null,
            null,
            $quarter,
        );
    }

    /**
     * How many weeks a given month offers, so the selector can hide an invalid
     * "5th Week" rather than let the user pick a date that does not exist.
     */
    public static function weeksInMonth(int $year, int $month): int
    {
        self::assertMonth($month);

        return CarbonImmutable::create($year, $month, 1)->daysInMonth >= self::FIFTH_WEEK_MIN_DAYS ? 5 : 4;
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? ucfirst($type);
    }

    public function startDate(): string
    {
        return $this->start->format('Y-m-d');
    }

    public function endDate(): string
    {
        return $this->end->format('Y-m-d');
    }

    public function rangeLabel(): string
    {
        return $this->start->format('M j, Y').' – '.$this->end->format('M j, Y');
    }

    /**
     * Human heading for the report, e.g. "October 2026 · 2nd Week",
     * "October 2026" or "Q4 2026".
     */
    public function label(): string
    {
        return match ($this->type) {
            self::WEEK => $this->start->format('F Y').' · '.self::ordinal($this->week).' Week',
            self::MONTH => $this->start->format('F Y'),
            self::QUARTER => 'Q'.$this->quarter.' '.$this->year,
            default => $this->rangeLabel(),
        };
    }

    /**
     * The exact query-string parameters that reproduce this selection, so the
     * report URL is reloadable.
     *
     * @return array<string, string|int>
     */
    public function query(): array
    {
        $params = ['period_type' => $this->type, 'year' => $this->year];

        if ($this->type === self::WEEK) {
            $params['month'] = $this->month;
            $params['week'] = $this->week;
        } elseif ($this->type === self::MONTH) {
            $params['month'] = $this->month;
        } elseif ($this->type === self::QUARTER) {
            $params['quarter'] = $this->quarter;
        }

        return $params;
    }

    public function contains(CarbonInterface|string $date): bool
    {
        $date = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->startOfDay()
            : CarbonImmutable::parse($date)->startOfDay();

        return $date->betweenIncluded($this->start, $this->end);
    }

    /**
     * The immediately preceding period of the SAME type, or null at the edge of
     * the supported year range. Weeks move within their month and roll to the
     * previous month's last week; months and quarters roll within the year.
     */
    public function previous(): ?self
    {
        return $this->shift(-1);
    }

    /**
     * The immediately following period of the SAME type, or null at the edge of
     * the supported year range.
     */
    public function next(): ?self
    {
        return $this->shift(1);
    }

    /**
     * Every period of the SAME type in the SAME year/quarter grouping, in
     * chronological order. For a week this is the weeks of that month; for a
     * month the twelve months; for a quarter the four quarters. Lets the view
     * render a compact selector without re-deriving the rules.
     *
     * @return list<self>
     */
    public function siblings(): array
    {
        return match ($this->type) {
            self::WEEK => $this->weekSiblings(),
            self::MONTH => array_map(fn (int $m) => self::month($this->year, $m), range(1, 12)),
            self::QUARTER => array_map(fn (int $q) => self::quarter($this->year, $q), range(1, 4)),
            default => [$this],
        };
    }

    private function shift(int $delta): ?self
    {
        try {
            return match ($this->type) {
                self::WEEK => $this->shiftWeek($delta),
                self::MONTH => $this->shiftMonth($delta),
                self::QUARTER => $this->shiftQuarter($delta),
                default => null,
            };
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function shiftWeek(int $delta): ?self
    {
        $week = (int) $this->week + $delta;
        $month = (int) $this->month;
        $year = $this->year;

        if ($week < 1) {
            // Roll back to the previous month's last available week.
            $month--;
            if ($month < 1) {
                $month = 12;
                $year--;
            }
            $week = self::weeksInMonth($year, $month);
        } elseif ($week > self::weeksInMonth($year, $month)) {
            // Roll forward to the next month's first week.
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
            $week = 1;
        }

        return self::week($year, $month, $week);
    }

    private function shiftMonth(int $delta): ?self
    {
        $month = (int) $this->month + $delta;
        $year = $this->year;

        if ($month < 1) {
            $month = 12;
            $year--;
        } elseif ($month > 12) {
            $month = 1;
            $year++;
        }

        return self::month($year, $month);
    }

    private function shiftQuarter(int $delta): ?self
    {
        $quarter = (int) $this->quarter + $delta;
        $year = $this->year;

        if ($quarter < 1) {
            $quarter = 4;
            $year--;
        } elseif ($quarter > 4) {
            $quarter = 1;
            $year++;
        }

        return self::quarter($year, $quarter);
    }

    /**
     * @return list<self>
     */
    private function weekSiblings(): array
    {
        $count = self::weeksInMonth($this->year, (int) $this->month);
        $weeks = [];

        for ($w = 1; $w <= $count; $w++) {
            $weeks[] = self::week($this->year, (int) $this->month, $w);
        }

        return $weeks;
    }

    private static function ordinal(?int $number): string
    {
        return match ($number) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            5 => '5th',
            default => (string) $number,
        };
    }

    private static function assertYear(int $year): void
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }
    }

    private static function assertMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid month: {$month}");
        }
    }

    private static function assertWeek(int $month, int $week, int $year): void
    {
        if ($week < 1 || $week > self::weeksInMonth($year, $month)) {
            throw new InvalidArgumentException(
                "Week {$week} does not exist in ".CarbonImmutable::create($year, $month, 1)->format('F Y').'.'
            );
        }
    }
}
