<?php

namespace Tests\Unit;

use App\Support\Bookkeeping\BookkeepingPeriod;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The month/quarter arithmetic the two new modules share. Everything else in
 * the modules is downstream of these answers, so they are pinned here rather
 * than only through HTTP tests.
 */
class BookkeepingPeriodTest extends TestCase
{
    // -----------------------------------------------------------------
    // Building a period
    // -----------------------------------------------------------------

    public function test_a_month_spans_its_whole_calendar_month(): void
    {
        $month = BookkeepingPeriod::monthly(2026, 2);

        $this->assertSame('2026-02-01', $month->startDate());
        $this->assertSame('2026-02-28', $month->endDate());
        $this->assertTrue($month->isMonthly());
        $this->assertFalse($month->isQuarterly());
    }

    public function test_a_month_knows_how_long_it_is(): void
    {
        $this->assertSame('2024-02-29', BookkeepingPeriod::monthly(2024, 2)->endDate());
        $this->assertSame('2026-01-31', BookkeepingPeriod::monthly(2026, 1)->endDate());
        $this->assertSame('2026-12-31', BookkeepingPeriod::monthly(2026, 12)->endDate());
    }

    public function test_an_impossible_month_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BookkeepingPeriod::monthly(2026, 13);
    }

    public function test_a_quarter_spans_three_whole_months(): void
    {
        $quarter = BookkeepingPeriod::quarterly(2026, 1);

        $this->assertSame('2026-01-01', $quarter->startDate());
        $this->assertSame('2026-03-31', $quarter->endDate());
        $this->assertTrue($quarter->isQuarterly());
        $this->assertSame(1, $quarter->quarterNumber());
    }

    public function test_every_quarter_ends_on_its_last_month(): void
    {
        $expected = [
            1 => ['2026-01-01', '2026-03-31'],
            2 => ['2026-04-01', '2026-06-30'],
            3 => ['2026-07-01', '2026-09-30'],
            4 => ['2026-10-01', '2026-12-31'],
        ];

        foreach ($expected as $number => [$start, $end]) {
            $quarter = BookkeepingPeriod::quarterly(2026, $number);

            $this->assertSame($start, $quarter->startDate(), "Q{$number} start");
            $this->assertSame($end, $quarter->endDate(), "Q{$number} end");
            $this->assertSame($number, $quarter->quarterNumber());
        }
    }

    public function test_an_impossible_quarter_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BookkeepingPeriod::quarterly(2026, 5);
    }

    public function test_an_unknown_period_kind_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BookkeepingPeriod::fromDate('yearly', '2026-03-17');
    }

    // -----------------------------------------------------------------
    // Normalising a loose date
    // -----------------------------------------------------------------

    public function test_any_date_falls_into_the_month_that_contains_it(): void
    {
        $fromString = BookkeepingPeriod::fromDate(BookkeepingPeriod::MONTHLY, '2026-03-17');
        $fromCarbon = BookkeepingPeriod::fromDate(BookkeepingPeriod::MONTHLY, CarbonImmutable::parse('2026-03-01 14:30'));

        $this->assertSame('2026-03', $fromString->key());
        $this->assertSame('2026-03-01', $fromString->startDate());
        $this->assertSame('2026-03-31', $fromString->endDate());
        $this->assertSame('2026-03', $fromCarbon->key());
    }

    public function test_any_date_falls_into_the_quarter_that_contains_it(): void
    {
        foreach ([
            '2026-01-01' => '2026-Q1',
            '2026-02-28' => '2026-Q1',
            '2026-03-31' => '2026-Q1',
            '2026-04-01' => '2026-Q2',
            '2026-06-15' => '2026-Q2',
            '2026-07-01' => '2026-Q3',
            '2026-09-30' => '2026-Q3',
            '2026-10-01' => '2026-Q4',
            '2026-12-31' => '2026-Q4',
        ] as $date => $expectedKey) {
            $quarter = BookkeepingPeriod::fromDate(BookkeepingPeriod::QUARTERLY, $date);

            $this->assertSame($expectedKey, $quarter->key(), "{$date} should land in {$expectedKey}");
        }
    }

    // -----------------------------------------------------------------
    // Normalising a key from a URL or a form
    // -----------------------------------------------------------------

    public function test_a_month_key_resolves_to_that_month(): void
    {
        $month = BookkeepingPeriod::fromKey(BookkeepingPeriod::MONTHLY, '2026-03');

        $this->assertSame('2026-03-01', $month->startDate());
        $this->assertSame('2026-03-31', $month->endDate());
    }

    public function test_a_quarter_key_resolves_to_that_quarter(): void
    {
        $quarter = BookkeepingPeriod::fromKey(BookkeepingPeriod::QUARTERLY, '2026-Q2');

        $this->assertSame('2026-04-01', $quarter->startDate());
        $this->assertSame('2026-06-30', $quarter->endDate());
    }

    public function test_a_quarter_key_is_case_insensitive(): void
    {
        $this->assertSame('2026-Q2', BookkeepingPeriod::fromKey(BookkeepingPeriod::QUARTERLY, '2026-q2')->key());
    }

    public function test_a_quarter_key_asked_for_by_the_monthly_module_stays_one_month(): void
    {
        $month = BookkeepingPeriod::fromKey(BookkeepingPeriod::MONTHLY, '2026-Q1');

        $this->assertSame('2026-01-01', $month->startDate());
        $this->assertSame('2026-01-31', $month->endDate());
    }

    public function test_a_month_key_asked_for_by_the_quarterly_module_widens_to_its_quarter(): void
    {
        $quarter = BookkeepingPeriod::fromKey(BookkeepingPeriod::QUARTERLY, '2026-02');

        $this->assertSame('2026-01-01', $quarter->startDate());
        $this->assertSame('2026-03-31', $quarter->endDate());
    }

    public function test_a_full_date_is_accepted_as_a_key(): void
    {
        $month = BookkeepingPeriod::fromKey(BookkeepingPeriod::MONTHLY, '2026-03-17');

        $this->assertSame('2026-03', $month->key());
    }

    public function test_an_unreadable_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BookkeepingPeriod::fromKey(BookkeepingPeriod::MONTHLY, 'not-a-period');
    }

    // -----------------------------------------------------------------
    // Keys, labels and query names
    // -----------------------------------------------------------------

    public function test_keys_and_labels_read_the_way_the_workbook_does(): void
    {
        $month = BookkeepingPeriod::monthly(2026, 3);

        $this->assertSame('2026-03', $month->key());
        $this->assertSame('March 2026', $month->label());
        $this->assertSame('Mar 2026', $month->shortLabel());
        $this->assertSame('Mar 1, 2026 – Mar 31, 2026', $month->rangeLabel());
        $this->assertSame('month', $month->queryName());

        $quarter = BookkeepingPeriod::quarterly(2026, 1);

        $this->assertSame('2026-Q1', $quarter->key());
        $this->assertSame('Q1 2026', $quarter->label());
        $this->assertSame('Q1 2026', $quarter->shortLabel());
        $this->assertSame('Jan 1, 2026 – Mar 31, 2026', $quarter->rangeLabel());
        $this->assertSame('quarter', $quarter->queryName());
    }

    public function test_to_array_carries_everything_a_view_needs(): void
    {
        $this->assertSame([
            'kind' => 'quarterly',
            'key' => '2026-Q4',
            'start' => '2026-10-01',
            'end' => '2026-12-31',
            'label' => 'Q4 2026',
            'short_label' => 'Q4 2026',
            'range_label' => 'Oct 1, 2026 – Dec 31, 2026',
        ], BookkeepingPeriod::quarterly(2026, 4)->toArray());
    }

    // -----------------------------------------------------------------
    // Moving around
    // -----------------------------------------------------------------

    public function test_previous_and_next_step_by_the_right_unit(): void
    {
        $this->assertSame('2026-02', BookkeepingPeriod::monthly(2026, 3)->previous()->key());
        $this->assertSame('2026-04', BookkeepingPeriod::monthly(2026, 3)->next()->key());
        $this->assertSame('2025-12', BookkeepingPeriod::monthly(2026, 1)->previous()->key());

        $this->assertSame('2025-Q4', BookkeepingPeriod::quarterly(2026, 1)->previous()->key());
        $this->assertSame('2026-Q2', BookkeepingPeriod::quarterly(2026, 1)->next()->key());
        $this->assertSame('2027-Q1', BookkeepingPeriod::quarterly(2026, 4)->next()->key());
    }

    public function test_stepping_over_a_year_end_stays_correct(): void
    {
        $this->assertSame('2025-12', BookkeepingPeriod::monthly(2026, 1)->previous()->key());
        $this->assertSame('2025-11', BookkeepingPeriod::monthly(2026, 1)->previous()->previous()->key());
        $this->assertSame('2025-Q3', BookkeepingPeriod::quarterly(2026, 1)->previous()->previous()->key());
    }

    public function test_only_the_running_period_is_current(): void
    {
        $this->assertTrue(BookkeepingPeriod::current(BookkeepingPeriod::MONTHLY)->isCurrent());
        $this->assertFalse(BookkeepingPeriod::monthly(2000, 1)->isCurrent());
        $this->assertTrue(BookkeepingPeriod::current(BookkeepingPeriod::QUARTERLY)->isCurrent());
        $this->assertFalse(BookkeepingPeriod::quarterly(2000, 1)->isCurrent());
    }

    // -----------------------------------------------------------------
    // Range checks
    // -----------------------------------------------------------------

    public function test_a_month_contains_only_its_own_days(): void
    {
        $march = BookkeepingPeriod::monthly(2026, 3);

        $this->assertTrue($march->contains('2026-03-01'));
        $this->assertTrue($march->contains('2026-03-15 23:59'));
        $this->assertTrue($march->contains('2026-03-31'));
        $this->assertFalse($march->contains('2026-02-28'));
        $this->assertFalse($march->contains('2026-04-01'));
    }

    public function test_a_quarter_contains_all_three_of_its_months(): void
    {
        $q1 = BookkeepingPeriod::quarterly(2026, 1);

        $this->assertTrue($q1->contains('2026-01-01'));
        $this->assertTrue($q1->contains('2026-02-14'));
        $this->assertTrue($q1->contains('2026-03-31'));
        $this->assertFalse($q1->contains('2026-04-01'));
    }

    // -----------------------------------------------------------------
    // Select options
    // -----------------------------------------------------------------

    public function test_options_run_oldest_to_newest_across_the_boundary(): void
    {
        $options = BookkeepingPeriod::quarterly(2026, 1)->options(1, 1);

        $this->assertSame(['2025-Q4', '2026-Q1', '2026-Q2'], array_keys($options));
        $this->assertSame('Q4 2025', $options['2025-Q4']);
        $this->assertSame('Q1 2026', $options['2026-Q1']);
        $this->assertSame('Q2 2026', $options['2026-Q2']);
    }

    public function test_options_default_to_a_year_back_and_two_quarters_ahead(): void
    {
        $options = BookkeepingPeriod::monthly(2026, 6)->options();

        $this->assertCount(19, $options);
        $this->assertSame('2025-06', array_key_first($options));
        $this->assertSame('2026-12', array_key_last($options));
    }

    public function test_options_can_show_only_the_present_and_the_future(): void
    {
        $options = BookkeepingPeriod::monthly(2026, 6)->options(0, 2);

        $this->assertSame(['2026-06', '2026-07', '2026-08'], array_keys($options));
    }
}
