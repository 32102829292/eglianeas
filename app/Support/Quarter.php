<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Immutable calendar-quarter value object used to filter the client's billing
 * and collections views by period. Keys follow the URL-friendly "2026-Q3"
 * format and are strictly validated on the server side.
 */
final class Quarter
{
    private const ORDINALS = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];

    public function __construct(
        public readonly int $year,
        public readonly int $quarter,
    ) {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Invalid quarter: {$quarter}");
        }
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }
    }

    /**
     * Parses a validated "2026-Q3" key, or returns null when the input is not
     * a well-formed calendar quarter.
     */
    public static function fromKey(?string $key): ?self
    {
        if ($key === null || trim($key) === '') {
            return null;
        }
        if (! preg_match('/^(\d{4})-Q([1-4])$/', trim($key), $matches)) {
            return null;
        }

        return new self((int) $matches[1], (int) $matches[2]);
    }

    public static function fromDate(CarbonInterface $date): self
    {
        return new self((int) $date->format('Y'), (int) ceil(((int) $date->format('n')) / 3));
    }

    public static function current(): self
    {
        return self::fromDate(now());
    }

    public function key(): string
    {
        return sprintf('%d-Q%d', $this->year, $this->quarter);
    }

    public function label(): string
    {
        return sprintf('Q%d %d', $this->quarter, $this->year);
    }

    public function longLabel(): string
    {
        return self::ORDINALS[$this->quarter].' Quarter '.$this->year;
    }

    /**
     * First day of the quarter (start of day).
     */
    public function start(): Carbon
    {
        return Carbon::create($this->year, (($this->quarter - 1) * 3) + 1, 1)->startOfDay();
    }

    /**
     * First day of the following quarter (exclusive bound for date ranges).
     */
    public function nextQuarterStart(): Carbon
    {
        return $this->start()->addMonths(3)->startOfDay();
    }

    /**
     * Last day of the quarter (start of day).
     */
    public function end(): Carbon
    {
        return $this->nextQuarterStart()->subDay()->startOfDay();
    }

    public function rangeLabel(): string
    {
        return $this->start()->format('M j, Y').' – '.$this->end()->format('M j, Y');
    }

    public function equals(?self $other): bool
    {
        return $other !== null
            && $other->year === $this->year
            && $other->quarter === $this->quarter;
    }
}