<?php

namespace Tests\Feature;

use App\Models\MonthlyBookkeeping;
use App\Models\MonthlyBookkeepingTarget;
use App\Support\Bookkeeping\BookkeepingPeriod;
use Illuminate\Support\Carbon;

/**
 * The monthly module is the period-based workflow running over whole calendar
 * months. All shared behaviour lives in PeriodBookkeepingTestCase; what is
 * asserted here is only what is specific to a month.
 */
class MonthlyBookkeepingTest extends PeriodBookkeepingTestCase
{
    protected function periodKind(): string
    {
        return BookkeepingPeriod::MONTHLY;
    }

    protected function planModel(): string
    {
        return MonthlyBookkeeping::class;
    }

    protected function targetModel(): string
    {
        return MonthlyBookkeepingTarget::class;
    }

    public function test_it_stores_the_whole_calendar_month(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $this->assertDatabaseHas($this->planTable(), [
            'staff_id' => $staff->id,
            'month_start' => $period->startDate().' 00:00:00',
            'month_end' => $period->endDate().' 00:00:00',
        ]);

        $plan = $this->findPlan($staff, $period);

        $this->assertSame(
            $period->startDate(),
            $plan->month_start->format('Y-m-d')
        );

        $this->assertSame(1, $plan->month_start->day, 'A monthly plan always starts on the 1st');

        $this->assertSame(
            Carbon::parse($period->startDate())->daysInMonth,
            $plan->month_end->day,
            'A monthly plan always ends on the last day of that month'
        );
    }

    public function test_the_month_query_key_navigates_months(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $this->actingAs($staff)
            ->get(route('admin.monthly-bookkeeping.index', ['month' => $period->key()]))
            ->assertOk()
            ->assertSee($period->rangeLabel());

        $this->actingAs($staff)
            ->get(route('admin.monthly-bookkeeping.index', ['month' => $period->previous()->key()]))
            ->assertOk()
            ->assertSee($period->previous()->rangeLabel())
            ->assertDontSee($period->rangeLabel());
    }

    public function test_a_target_in_another_month_cannot_enter_this_month(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        // The first day of the following month is outside this month plan.
        $outside = Carbon::parse($period->next()->startDate());

        $this->actingAs($staff)
            ->post(route('admin.monthly-bookkeeping.store'), [
                'month_start' => $period->startDate(),
                'clients' => [$client->id],
                'tasks' => [$client->id => ['pickup']],
                'target_date' => [$client->id => $outside->format('Y-m-d')],
            ])
            ->assertSessionHasErrors('target_date.'.$client->id);

        $this->assertSame(0, MonthlyBookkeeping::query()->count());
    }

    public function test_moving_a_target_into_another_month_is_rejected(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);
        $target = $this->target($plan);

        $outside = Carbon::parse($period->next()->startDate());

        $this->actingAs($staff)
            ->patch(route('admin.monthly-bookkeeping.update-target', [$plan, $target]), [
                'target_date' => $outside->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('target_date');

        $target->refresh();
        $this->assertSame($period->startDate(), $target->target_date->format('Y-m-d'));
    }

    public function test_a_month_may_hold_several_task_types_for_one_client(): void
    {
        $staff = $this->staff();
        $client = $this->client('Villasin, Maricel');

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => [
            'pickup', 'record', 'return', 'payment',
        ]]]);

        $this->assertSame(4, $plan->targets()->count());
        $this->assertSame(1, $plan->targetClientCount());

        foreach (['pickup', 'record', 'return', 'payment'] as $taskType) {
            $this->assertTrue($plan->targets()->where('task_type', $taskType)->exists());
        }
    }

    public function test_the_period_column_is_named_for_a_month(): void
    {
        $this->assertSame('month_start', MonthlyBookkeeping::PERIOD_START_FIELD);
        $this->assertSame('month_start', MonthlyBookkeeping::PERIOD_START_COLUMN);
        $this->assertSame('monthly_bookkeeping_id', MonthlyBookkeeping::FOREIGN_KEY);
        $this->assertSame('admin.monthly-bookkeeping', MonthlyBookkeeping::ROUTE_PREFIX);
        $this->assertSame('Month', (new MonthlyBookkeeping)->periodUnit());
        $this->assertSame('Months', (new MonthlyBookkeeping)->periodUnitPlural());
    }
}
