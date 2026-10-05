<?php

namespace Tests\Feature;

use App\Models\QuarterlyBookkeeping;
use App\Models\QuarterlyBookkeepingTarget;
use App\Support\Bookkeeping\BookkeepingPeriod;
use Illuminate\Support\Carbon;

/**
 * The quarterly module runs the same workflow over three calendar months at a
 * time. Only quarter-specific behaviour is asserted here; the shared rules live
 * in PeriodBookkeepingTestCase.
 */
class QuarterlyBookkeepingTest extends PeriodBookkeepingTestCase
{
    protected function periodKind(): string
    {
        return BookkeepingPeriod::QUARTERLY;
    }

    protected function planModel(): string
    {
        return QuarterlyBookkeeping::class;
    }

    protected function targetModel(): string
    {
        return QuarterlyBookkeepingTarget::class;
    }

    public function test_it_stores_three_whole_months(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $this->assertDatabaseHas($this->planTable(), [
            'staff_id' => $staff->id,
            'quarter_start' => $period->startDate().' 00:00:00',
            'quarter_end' => $period->endDate().' 00:00:00',
        ]);

        $plan = $this->findPlan($staff, $period);

        $this->assertSame(1, $plan->quarter_start->day, 'A quarter starts on the 1st');

        $this->assertSame(
            3,
            (int) $plan->quarter_start->diffInMonths($plan->quarter_end) + 1,
            'A quarter spans exactly three months'
        );

        $this->assertTrue($plan->period()->contains($plan->period()->endDate()));
    }

    public function test_the_quarter_query_key_navigates_quarters(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $this->assertStringStartsWith((string) now()->year, $period->key());
        $this->assertMatchesRegularExpression('/^\d{4}-Q[1-4]$/', $period->key());

        $this->actingAs($staff)
            ->get(route('admin.quarterly-bookkeeping.index', ['quarter' => $period->key()]))
            ->assertOk()
            ->assertSee($period->rangeLabel());

        $this->actingAs($staff)
            ->get(route('admin.quarterly-bookkeeping.index', ['quarter' => $period->previous()->key()]))
            ->assertOk()
            ->assertSee($period->previous()->rangeLabel())
            ->assertDontSee($period->rangeLabel());
    }

    public function test_each_quarter_accepts_work_from_all_three_of_its_months(): void
    {
        $staff = $this->staff();
        $period = $this->currentPeriod();
        $client = $this->client();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $target = $this->target($plan);
        $start = Carbon::parse($period->startDate());

        // Walk a target date forward month by month; all three stay legal.
        for ($step = 0; $step < 3; $step++) {
            $date = $start->copy()->addMonths($step);

            $this->actingAs($staff)
                ->patch(route('admin.quarterly-bookkeeping.update-target', [$plan, $target]), [
                    'target_date' => $date->format('Y-m-d'),
                ])
                ->assertRedirect();

            $target->refresh();
            $this->assertSame($date->format('Y-m-d'), $target->target_date->format('Y-m-d'));
        }
    }

    public function test_a_target_in_the_next_quarter_cannot_enter_this_one(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        // One day past the quarter's last day belongs to the next quarter.
        $outside = Carbon::parse($period->endDate())->addDay();

        $this->actingAs($staff)
            ->post(route('admin.quarterly-bookkeeping.store'), [
                'quarter_start' => $period->startDate(),
                'clients' => [$client->id],
                'tasks' => [$client->id => ['pickup']],
                'target_date' => [$client->id => $outside->format('Y-m-d')],
            ])
            ->assertSessionHasErrors('target_date.'.$client->id);

        $this->assertSame(0, QuarterlyBookkeeping::query()->count());
    }

    public function test_a_month_key_resolves_to_the_quarter_containing_it(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        // A mid-quarter month is accepted as a way of naming that quarter.
        $middleMonth = Carbon::parse($period->startDate())->addMonth();

        $this->actingAs($staff)
            ->post(route('admin.quarterly-bookkeeping.store'), [
                'quarter_start' => $middleMonth->format('Y-m-d'),
                'clients' => [$client->id],
                'tasks' => [$client->id => ['pickup']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas($this->planTable(), [
            'quarter_start' => $period->startDate().' 00:00:00',
            'quarter_end' => $period->endDate().' 00:00:00',
        ]);

        $this->assertSame(1, QuarterlyBookkeeping::query()->count());
    }

    public function test_a_quarter_key_asked_for_by_the_monthly_module_stays_a_month(): void
    {
        // A quarterly key must not silently widen a monthly plan to three months.
        $period = BookkeepingPeriod::fromKey(BookkeepingPeriod::MONTHLY, '2026-Q1');

        $this->assertSame('2026-01', $period->key());
        $this->assertSame('2026-01-01', $period->startDate());
        $this->assertSame('2026-01-31', $period->endDate());
    }

    public function test_a_month_key_asked_for_by_the_quarterly_module_widens_to_its_quarter(): void
    {
        $period = BookkeepingPeriod::fromKey(BookkeepingPeriod::QUARTERLY, '2026-02');

        $this->assertSame('2026-Q1', $period->key());
        $this->assertSame('2026-01-01', $period->startDate());
        $this->assertSame('2026-03-31', $period->endDate());
    }

    public function test_the_period_column_is_named_for_a_quarter(): void
    {
        $this->assertSame('quarter_start', QuarterlyBookkeeping::PERIOD_START_FIELD);
        $this->assertSame('quarter_start', QuarterlyBookkeeping::PERIOD_START_COLUMN);
        $this->assertSame('quarterly_bookkeeping_id', QuarterlyBookkeeping::FOREIGN_KEY);
        $this->assertSame('admin.quarterly-bookkeeping', QuarterlyBookkeeping::ROUTE_PREFIX);
        $this->assertSame('Quarter', (new QuarterlyBookkeeping)->periodUnit());
        $this->assertSame('Quarters', (new QuarterlyBookkeeping)->periodUnitPlural());
    }
}
