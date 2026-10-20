<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\User;
use App\Models\WeeklyBookkeeping;
use App\Models\WeeklyBookkeepingTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STAFF,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $name = 'Test Client'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'name' => $name,
            'business_name' => "{$name} Business",
            'approved_at' => now(),
        ]);
    }

    private function weekStart(): string
    {
        return now()->startOfWeek()->format('Y-m-d');
    }

    private function plan(string $week): WeeklyBookkeeping
    {
        return WeeklyBookkeeping::query()->create([
            'week_start' => $week,
            'week_end' => Carbon::parse($week)->endOfWeek()->format('Y-m-d'),
            'status' => WeeklyBookkeeping::STATUS_NOT_STARTED,
        ]);
    }

    private function target(WeeklyBookkeeping $plan, User $client, string $type, string $date, array $attributes = []): WeeklyBookkeepingTarget
    {
        return WeeklyBookkeepingTarget::query()->create(array_merge([
            'weekly_bookkeeping_id' => $plan->id,
            'client_id' => $client->id,
            'task_type' => $type,
            'target_date' => $date,
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.weekly-tracker.index'))->assertRedirect(route('login'));
    }

    public function test_client_cannot_access_the_tracker(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('admin.weekly-tracker.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_task(): void
    {
        $admin = $this->admin();
        $client = $this->client('Acme Trading');
        $week = $this->weekStart();

        $this->actingAs($admin)
            ->post(route('admin.weekly-tracker.store'), [
                'client_id' => $client->id,
                'target_date' => $week,
                'task_type' => 'pickup',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('weekly_bookkeeping_targets', [
            'client_id' => $client->id,
            'target_date' => $week.' 00:00:00',
            'task_type' => 'pickup',
            'actual_status' => 'pending',
        ]);

        $this->assertDatabaseHas('weekly_bookkeeping', [
            'week_start' => $week.' 00:00:00',
        ]);
    }

    public function test_duplicate_task_for_same_client_date_and_type_is_rejected(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $week = $this->weekStart();

        $plan = $this->plan($week);
        $this->target($plan, $client, 'pickup', $week);

        $this->actingAs($admin)
            ->post(route('admin.weekly-tracker.store'), [
                'client_id' => $client->id,
                'target_date' => $week,
                'task_type' => 'pickup',
            ])
            ->assertSessionHasErrors('task_type');

        $this->assertSame(1, WeeklyBookkeepingTarget::query()->count());
    }

    public function test_pickup_stage_update_persists_after_refresh(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $today = now()->format('Y-m-d');
        $target = $this->target($this->plan($today), $client, 'pickup', $today);

        $this->actingAs($admin)
            ->patch(route('admin.weekly-tracker.update-stage', $target), [
                'stage' => 'pickup',
                'status' => 'on_time',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('on_time', $target->actual_status);
        $this->assertNotNull($target->ended_at);
        $this->assertSame('on_time', $target->timing);
    }

    public function test_reverting_a_stage_clears_the_actual_date(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $week = $this->weekStart();
        $target = $this->target($this->plan($week), $client, 'pickup', $week, [
            'actual_status' => 'on_time',
            'ended_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.weekly-tracker.update-stage', $target), [
                'stage' => 'pickup',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('pending', $target->actual_status);
        $this->assertNull($target->ended_at);
    }

    public function test_billing_paid_captures_payment_method_and_revert_clears_it(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $week = $this->weekStart();
        $target = $this->target($this->plan($week), $client, 'payment', $week);

        $this->actingAs($admin)
            ->patch(route('admin.weekly-tracker.update-stage', $target), [
                'stage' => 'payment',
                'status' => 'paid',
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('paid', $target->actual_status);
        $this->assertSame('cash', $target->payment_method);
        $this->assertNotNull($target->payment_at);

        $this->actingAs($admin)
            ->patch(route('admin.weekly-tracker.update-stage', $target), [
                'stage' => 'payment',
                'status' => 'unpaid',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('unpaid', $target->actual_status);
        $this->assertNull($target->payment_method);
        $this->assertNull($target->payment_at);
    }

    public function test_invalid_status_for_a_stage_is_rejected(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $week = $this->weekStart();
        $target = $this->target($this->plan($week), $client, 'pickup', $week);

        $this->actingAs($admin)
            ->patch(route('admin.weekly-tracker.update-stage', $target), [
                'stage' => 'pickup',
                'status' => 'returned',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $target->fresh()->actual_status);
    }

    public function test_index_shows_weekday_groups_and_summary_counts(): void
    {
        $admin = $this->admin();
        $client = $this->client('Acme Trading');
        $week = now()->startOfWeek();
        $plan = $this->plan($week->format('Y-m-d'));

        $this->target($plan, $client, 'pickup', $week->format('Y-m-d'), [
            'actual_status' => 'on_time',
            'ended_at' => $week,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.weekly-tracker.index', ['week_start' => $week->format('Y-m-d')]))
            ->assertOk();

        $response->assertSee('Monday');
        $response->assertSee('Sunday');
        $response->assertSee('Acme Trading Business');
        $response->assertSee('Target Date');
        $response->assertSee('Payment Date');
    }

    public function test_index_shows_the_reference_week_heading_and_stage_bands(): void
    {
        $admin = $this->admin();
        $client = $this->client('Acme Trading');
        $week = now()->startOfWeek();
        $plan = $this->plan($week->format('Y-m-d'));

        $this->target($plan, $client, 'pickup', $week->format('Y-m-d'));

        $expectedHeading = \App\Http\Controllers\Admin\WeeklyTrackerController::weekHeading($week);

        $response = $this->actingAs($admin)
            ->get(route('admin.weekly-tracker.index', ['week_start' => $week->format('Y-m-d')]))
            ->assertOk();

        /* The comparison heading, e.g. "2nd Week of October 2026". */
        $response->assertSee($expectedHeading);
        $response->assertSee('Week of '.$week->format('F Y'));

        /* Within the day, clients are grouped under their process stage. */
        $response->assertSee('Pick-Up');
        $response->assertSee('Record');
        $response->assertSee('Return / Collect');
        $response->assertSee('Billing / Payment');
    }

    public function test_week_heading_names_the_week_by_its_start_month(): void
    {
        $controller = \App\Http\Controllers\Admin\WeeklyTrackerController::class;

        /* Each week is named for the month its Monday falls in, using the
           workbook's ordinals. Oct 5 2026 is the first Monday of October, so
           that week is "1st"; Oct 12 starts the "2nd", and so on. A date later
           in the week resolves to the same Monday, so Oct 8 belongs to the 1st. */
        $this->assertSame('1st Week of October 2026', $controller::weekHeading(Carbon::parse('2026-10-05')));
        $this->assertSame('1st Week of October 2026', $controller::weekHeading(Carbon::parse('2026-10-08')));
        $this->assertSame('2nd Week of October 2026', $controller::weekHeading(Carbon::parse('2026-10-12')));
        $this->assertSame('4th Week of October 2026', $controller::weekHeading(Carbon::parse('2026-10-29')));
        $this->assertSame('4th Week of September 2026', $controller::weekHeading(Carbon::parse('2026-10-01')));
    }

    public function test_plan_cta_links_to_the_multi_client_planner(): void
    {
        $admin = $this->admin();
        $week = now()->startOfWeek()->format('Y-m-d');

        $this->actingAs($admin)
            ->get(route('admin.weekly-tracker.index', ['week_start' => $week]))
            ->assertOk()
            ->assertSee(
                route('admin.weekly-bookkeeping.create', ['week_start' => $week]),
                false
            );
    }

    public function test_index_filters_by_client_and_task_type(): void
    {
        $admin = $this->admin();
        $clientA = $this->client('Alpha');
        $clientB = $this->client('Beta');
        $week = now()->startOfWeek()->format('Y-m-d');
        $plan = $this->plan($week);

        $targetA = $this->target($plan, $clientA, 'pickup', $week);
        $targetB = $this->target($plan, $clientB, 'record', $week);

        $stageUrlA = route('admin.weekly-tracker.update-stage', $targetA);
        $stageUrlB = route('admin.weekly-tracker.update-stage', $targetB);

        $this->actingAs($admin)
            ->get(route('admin.weekly-tracker.index', [
                'week_start' => $week,
                'client' => $clientB->id,
            ]))
            ->assertOk()
            ->assertSee($stageUrlB, false)
            ->assertDontSee($stageUrlA, false);

        $this->actingAs($admin)
            ->get(route('admin.weekly-tracker.index', [
                'week_start' => $week,
                'task_type' => 'record',
            ]))
            ->assertOk()
            ->assertSee($stageUrlB, false)
            ->assertDontSee($stageUrlA, false);
    }

    public function test_staff_cannot_delete_but_admin_can(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $week = $this->weekStart();
        $target = $this->target($this->plan($week), $client, 'pickup', $week);

        $this->actingAs($staff)
            ->delete(route('admin.weekly-tracker.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('weekly_bookkeeping_targets', ['id' => $target->id]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->delete(route('admin.weekly-tracker.destroy', $target))
            ->assertRedirect();

        $this->assertDatabaseMissing('weekly_bookkeeping_targets', ['id' => $target->id]);
    }

    public function test_empty_week_shows_empty_state(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.weekly-tracker.index', ['week_start' => now()->startOfWeek()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('No tasks this week');
    }

    public function test_weekly_bookkeeping_index_still_renders_its_board(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index'))
            ->assertOk();
    }
}
