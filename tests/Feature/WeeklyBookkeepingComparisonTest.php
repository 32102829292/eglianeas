<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\User;
use App\Models\WeeklyBookkeeping;
use App\Models\WeeklyBookkeepingTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The comparison workbook ("Comparison of weekly bookkeeping status") is the
 * reference for this behaviour: one sheet per week, per-task staff assignment,
 * TARGET vs ACTUAL wording, and separate Uncollected / Unfinished and Unpaid
 * columns.
 */
class WeeklyBookkeepingComparisonTest extends TestCase
{
    use RefreshDatabase;

    /** Monday 28 September 2026 – Sunday 4 October 2026, the scenario week. */
    private const WEEK = '2026-09-28';

    private const TODAY = '2026-09-29';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('supabase');
        $this->travelTo(\Illuminate\Support\Carbon::parse(self::TODAY)->setTime(10, 0));
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function staff(string $name): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STAFF,
            'name' => $name,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function supervisor(string $name = 'Supervisor'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'name' => $name,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $name): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'name' => $name,
            'business_name' => $name,
        ]);
    }

    /**
     * Plans the exact scenario from the specification:
     *
     *   Client A — Pick-Up Maria, Record Juan, Return Maria, Billing Maria
     *   Client B — Pick-Up Maria, Record Juan
     *   Client C — Record Juan
     */
    private function planScenarioWeek(User $planner): WeeklyBookkeeping
    {
        $maria = $this->staff('Maria Santos');
        $juan = $this->staff('Juan Dela Cruz');

        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $clientC = $this->client('Client C');

        $this->actingAs($planner)->post(route('admin.weekly-bookkeeping.store'), [
            'week_start' => self::WEEK,
            'clients' => [$clientA->id, $clientB->id, $clientC->id],
            'tasks' => [
                $clientA->id => ['pickup', 'record', 'return', 'payment'],
                $clientB->id => ['pickup', 'record'],
                $clientC->id => ['record'],
            ],
            'target_date' => [
                $clientA->id => self::TODAY,
                $clientB->id => self::TODAY,
                $clientC->id => self::TODAY,
            ],
            'assignee' => [
                $clientA->id => [
                    'pickup' => $maria->id,
                    'record' => $juan->id,
                    'return' => $maria->id,
                    'payment' => $maria->id,
                ],
                $clientB->id => [
                    'pickup' => $maria->id,
                    'record' => $juan->id,
                ],
                $clientC->id => [
                    'record' => $juan->id,
                ],
            ],
        ])->assertRedirect();

        return WeeklyBookkeeping::query()->whereDate('week_start', self::WEEK)->firstOrFail();
    }

    private function targetsFor(User $staff): \Illuminate\Support\Collection
    {
        return WeeklyBookkeepingTarget::query()
            ->where('assigned_staff_id', $staff->id)
            ->with('client')
            ->get()
            ->sortBy(fn ($t) => [$t->client->name, $t->task_type])
            ->values();
    }

    private function completeWithEvidence(User $actor, WeeklyBookkeepingTarget $target, array $extra = []): void
    {
        $this->actingAs($actor)
            ->post(route('admin.weekly-bookkeeping.start-target', [$target->weekly_bookkeeping_id, $target]));

        $this->actingAs($actor)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$target->weekly_bookkeeping_id, $target]), array_merge([
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ], $extra))
            ->assertRedirect();
    }

    public function test_each_task_carries_its_own_assigned_staff(): void
    {
        $this->planScenarioWeek($this->admin());

        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        $pickup = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'pickup')->firstOrFail();
        $record = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'record')->firstOrFail();
        $return = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'return')->firstOrFail();
        $payment = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'payment')->firstOrFail();

        // A single client can have different staff per task.
        $this->assertSame('Maria Santos', $pickup->assignedStaffDisplayName());
        $this->assertSame('Juan Dela Cruz', $record->assignedStaffDisplayName());
        $this->assertSame('Maria Santos', $return->assignedStaffDisplayName());
        $this->assertSame('Maria Santos', $payment->assignedStaffDisplayName());

        $this->assertNotSame($pickup->assigned_staff_id, $record->assigned_staff_id);
    }

    public function test_record_only_staff_sees_only_record_tasks(): void
    {
        $this->planScenarioWeek($this->admin());

        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $tasks = $this->targetsFor($juan);

        $this->assertCount(3, $tasks, 'Juan is assigned three Record tasks.');
        $this->assertSame(['record'], $tasks->pluck('task_type')->unique()->values()->all());
        $this->assertSame(['Client A', 'Client B', 'Client C'], $tasks->pluck('client.name')->all());
    }

    public function test_pickup_return_payment_staff_sees_only_their_own_tasks(): void
    {
        $this->planScenarioWeek($this->admin());

        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $tasks = $this->targetsFor($maria);

        $this->assertCount(4, $tasks);
        $this->assertEqualsCanonicalizing(
            ['pickup', 'pickup', 'return', 'payment'],
            $tasks->pluck('task_type')->all()
        );

        $byType = $tasks->groupBy('task_type');
        $this->assertEqualsCanonicalizing(['Client A', 'Client B'], $byType['pickup']->pluck('client.name')->all());
        $this->assertEqualsCanonicalizing(['Client A'], $byType['return']->pluck('client.name')->all());
        $this->assertEqualsCanonicalizing(['Client A'], $byType['payment']->pluck('client.name')->all());
    }

    public function test_staff_cannot_see_or_act_on_tasks_assigned_to_someone_else(): void
    {
        $plan = $this->planScenarioWeek($this->admin());

        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();
        $mariaPickup = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'pickup')->firstOrFail();

        $this->assertNotSame($juan->id, $mariaPickup->assigned_staff_id);

        // Juan's tracker does not surface Maria's Pick-Up work.
        $this->actingAs($juan)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('rows', function ($rows) use ($mariaPickup, $juan) {
                $ids = collect($rows)->pluck('id');

                return $ids->contains($mariaPickup->id) === false
                    && $ids->count() === 3;
            });

        // And he cannot act on it.
        $this->actingAs($juan)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $mariaPickup]))
            ->assertForbidden();
    }

    public function test_admin_and_supervisor_see_the_entire_week(): void
    {
        $this->planScenarioWeek($this->admin());

        foreach ([$this->admin(), $this->supervisor()] as $oversight) {
            $response = $this->actingAs($oversight)
                ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]));

            $response->assertOk();
            $response->assertSee('Client A');
            $response->assertSee('Client B');
            $response->assertSee('Client C');
        }
    }

    public function test_supervisor_can_perform_assigned_work(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $supervisor = $this->supervisor();

        $clientA = User::query()->where('name', 'Client A')->firstOrFail();
        $pickup = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'pickup')->firstOrFail();

        $this->completeWithEvidence($supervisor, $pickup);

        $pickup->refresh();
        $this->assertTrue($pickup->isCompleted());
        $this->assertSame($supervisor->id, $pickup->performed_by_id);
    }

    public function test_recording_and_pickup_stay_separate_tasks(): void
    {
        $plan = $this->planScenarioWeek($this->admin());

        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        $record = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'record')->firstOrFail();
        $pickup = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'pickup')->firstOrFail();

        // Juan completes Record.
        $this->completeWithEvidence($juan, $record);

        $record->refresh();
        $pickup->refresh();

        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_COMPLETED, $record->actual_status);
        $this->assertSame('Juan Dela Cruz', $record->performedByDisplayName());
        $this->assertSame(WeeklyBookkeepingTarget::TIMING_ON_TIME, $record->timing);

        // The Pick-Up task is untouched and still pending.
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING, $pickup->actual_status);
        $this->assertNull($pickup->performed_by_id);

        // Maria completes Pick-Up.
        $this->completeWithEvidence($maria, $pickup);

        $pickup->refresh();
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_ON_TIME, $pickup->actual_status);
        $this->assertSame('Maria Santos', $pickup->performedByDisplayName());

        // Both tasks remain distinct records.
        $this->assertNotSame($record->id, $pickup->id);
        $this->assertNotSame($record->performed_by_id, $pickup->performed_by_id);
    }

    public function test_task_resolves_to_its_own_workbook_wording(): void
    {
        $this->planScenarioWeek($this->admin());

        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        $expected = [
            'pickup' => [WeeklyBookkeepingTarget::ACTUAL_STATUS_ON_TIME, 'On Time'],
            'record' => [WeeklyBookkeepingTarget::ACTUAL_STATUS_COMPLETED, 'Completed'],
            'return' => [WeeklyBookkeepingTarget::ACTUAL_STATUS_RETURNED, 'Returned'],
            'payment' => [WeeklyBookkeepingTarget::ACTUAL_STATUS_PAID, 'Paid'],
        ];

        foreach ($expected as $taskType => [$status, $label]) {
            $target = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
                ->where('task_type', $taskType)
                ->firstOrFail();

            $actor = $taskType === 'record' ? $juan : $maria;
            $this->completeWithEvidence($actor, $target, $taskType === 'payment' ? ['payment_method' => 'cash'] : []);

            $target->refresh();
            $this->assertSame($status, $target->actual_status, "{$label} status for {$taskType}");
            $this->assertSame($label, $target->effectiveStatusLabel());
            $this->assertTrue($target->isCompleted());
        }
    }

    public function test_payment_records_method_and_date_without_inventing_them(): void
    {
        $this->planScenarioWeek($this->admin());

        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();
        $payment = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'payment')->firstOrFail();

        $this->assertNull($payment->payment_method);
        $this->assertNull($payment->paymentLabel());

        $this->completeWithEvidence($maria, $payment, ['payment_method' => 'gcash']);

        $payment->refresh();
        $this->assertSame('Paid GCash', $payment->paymentLabel());
        $this->assertNotNull($payment->payment_at);
        $this->assertStringContainsString('Paid GCash', (string) $payment->paymentDetail());
    }

    public function test_work_finished_after_the_target_date_is_late_not_just_completed(): void
    {
        $this->planScenarioWeek($this->admin());

        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $clientB = User::query()->where('name', 'Client B')->firstOrFail();
        $record = WeeklyBookkeepingTarget::where('client_id', $clientB->id)->where('task_type', 'record')->firstOrFail();

        // Move the target to yesterday, then complete today.
        $record->update(['target_date' => '2026-09-28']);

        $this->completeWithEvidence($juan, $record);

        $record->refresh();
        $this->assertSame(WeeklyBookkeepingTarget::TIMING_LATE, $record->timing);
        $this->assertTrue($record->isLate());
        $this->assertSame('Completed Late', $record->timingLabel());
        // Still completed, but visibly against target.
        $this->assertTrue($record->isCompleted());
    }

    public function test_unfinished_and_unpaid_filters_separate_outstanding_work(): void
    {
        $plan = $this->planScenarioWeek($this->admin());

        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        // Complete only Client A's Record, exactly as the scenario ends.
        $record = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'record')->firstOrFail();
        $this->completeWithEvidence($juan, $record);

        $admin = $this->admin();

        $unfinished = $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK, 'view' => 'unfinished']));
        $unfinished->assertOk();
        $unfinished->assertSee('Uncollected / Unfinished');
        $unfinished->assertSee('Client A');

        $unpaid = $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK, 'view' => 'unpaid']));
        $unpaid->assertOk();
        $unpaid->assertSee('Unpaid');

        // Billing still outstanding for Client A.
        $payment = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'payment')->firstOrFail();
        $this->assertTrue($payment->isUnpaid());

        // Once paid it leaves the unpaid list.
        $this->completeWithEvidence($maria, $payment, ['payment_method' => 'cash']);
        $payment->refresh();
        $this->assertFalse($payment->isUnpaid());
    }

    public function test_tracker_grid_matches_the_workbook_columns(): void
    {
        $this->planScenarioWeek($this->admin());
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]));

        $response->assertOk();
        // Comparison sheet headers.
        foreach (['Target Date', 'Task Type', 'Client Name', 'Pick-Up', 'Record', 'Return', 'Billing'] as $header) {
            $response->assertSee($header);
        }
        // Weekly summary counters as shown on the operations dashboard.
        foreach (['Clients', 'Tasks', 'Completed', 'Pending', 'Attention'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_weekly_dashboard_summary_counts_the_scenario_week(): void
    {
        $this->planScenarioWeek($this->admin());

        $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['clients'] === 3
                && $summary['tasks'] === 7
                && $summary['completed'] === 0
                && $summary['pending'] === 7
                // Client A's Billing task is the one still-unpaid item, so it is
                // the only thing flagged while the week is still open.
                && $summary['attention'] === 1);
    }

    public function test_task_list_groups_tasks_by_client_with_real_counts(): void
    {
        $this->planScenarioWeek($this->admin());

        $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('clientGroups', function ($groups) {
                $names = $groups->pluck('name')->sort()->values()->all();

                return $groups->count() === 3
                    && $names === ['Client A', 'Client B', 'Client C']
                    && $groups->firstWhere('name', 'Client A')['total'] === 4
                    && $groups->firstWhere('name', 'Client B')['total'] === 2
                    && $groups->firstWhere('name', 'Client C')['total'] === 1
                    && $groups->sum('total') === 7;
            });
    }

    public function test_todays_priorities_lists_work_that_is_due_today(): void
    {
        $this->planScenarioWeek($this->admin());

        $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            // Every target in the scenario is dated today and still open, and the
            // flagged Billing task leads the list.
            ->assertViewHas('todayItems', fn ($items) => $items->count() === 7
                && $items->first()['status'] === 'attention'
                && $items->pluck('client_name')->unique()->count() === 3);
    }

    public function test_todays_priorities_shows_the_all_clear_state_when_nothing_is_due(): void
    {
        $client = $this->client('Quiet Client');
        $juan = $this->staff('Juan Dela Cruz');

        // A Record-only week dated later in the week: nothing due today, nothing
        // unpaid and nobody missing, so the board has nothing to escalate.
        $this->actingAs($this->admin())->post(route('admin.weekly-bookkeeping.store'), [
            'week_start' => self::WEEK,
            'clients' => [$client->id],
            'tasks' => [$client->id => ['record']],
            'target_date' => [$client->id => '2026-10-02'],
            'assignee' => [$client->id => ['record' => $juan->id]],
        ])->assertRedirect();

        $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['attention'] === 0)
            ->assertViewHas('todayItems', fn ($items) => $items->isEmpty())
            ->assertSee('Nothing requires attention today.')
            ->assertSee('1 task still scheduled this week.');
    }

    public function test_attention_bucket_catches_work_left_past_the_week(): void
    {
        $this->planScenarioWeek($this->admin());

        // Once the week closes, every unfinished task needs a person.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-05')->setTime(9, 0));

        $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['attention'] === 7)
            ->assertViewHas('clientGroups', function ($groups) {
                return $groups->every(fn ($group) => $group['attention'] === $group['total']);
            });
    }

    public function test_unassigned_task_is_flagged_for_attention(): void
    {
        $this->planScenarioWeek($this->admin());

        $clientC = User::query()->where('name', 'Client C')->firstOrFail();
        $record = WeeklyBookkeepingTarget::where('client_id', $clientC->id)
            ->where('task_type', 'record')
            ->firstOrFail();
        $record->update(['assigned_staff_id' => null, 'assigned_staff_name' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            // The already-unpaid Billing task plus the newly unassigned Record task.
            ->assertViewHas('summary', fn ($summary) => $summary['attention'] === 2)
            ->assertViewHas('clientGroups', function ($groups) use ($clientC, $record) {
                $group = $groups->firstWhere('client_id', $clientC->id);
                $item = $group['items']->firstWhere('id', $record->id);

                return $group['attention'] === 1
                    && $item['status'] === 'attention'
                    && $item['needs_attention'] === true
                    && $item['staff_name'] === ''
                    // An admin covers the whole week, so the task is still theirs to run.
                    && $item['can_start'] === true;
            });

        // The former assignee is no longer offered it, and it leaves their board.
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();

        $this->actingAs($juan)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('clientGroups', function ($groups) use ($record) {
                return $groups->flatMap(fn ($group) => $group['items'])
                    ->pluck('id')
                    ->contains($record->id) === false;
            });
    }

    public function test_attention_filter_narrows_the_board_to_flagged_work(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-05')->setTime(9, 0));

        $admin = $this->admin();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();
        $pickup = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
            ->where('task_type', 'pickup')
            ->firstOrFail();

        $this->completeWithEvidence($admin, $pickup);

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK, 'view' => 'attention']))
            ->assertOk()
            // The finished Pick-Up drops out of the attention board.
            ->assertViewHas('summary', fn ($summary) => $summary['tasks'] === 6 && $summary['attention'] === 6)
            ->assertViewHas('clientGroups', function ($groups) use ($pickup) {
                return $groups->flatMap(fn ($group) => $group['items'])
                    ->pluck('id')
                    ->contains($pickup->id) === false;
            });
    }

    public function test_start_action_is_only_offered_on_the_viewers_own_tasks(): void
    {
        $this->planScenarioWeek($this->admin());

        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();
        $mariaPickup = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
            ->where('task_type', 'pickup')
            ->firstOrFail();

        $this->actingAs($juan)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('clientGroups', function ($groups) use ($mariaPickup) {
                $startable = $groups->flatMap(fn ($group) => $group['items'])
                    ->filter(fn ($item) => $item['can_start']);

                // Juan is named on three Record tasks and nothing else.
                return $startable->count() === 3
                    && $startable->pluck('id')->contains($mariaPickup->id) === false;
            });
    }

    public function test_weekly_progress_breaks_down_by_task_type(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $admin = $this->admin();

        $clientA = User::query()->where('name', 'Client A')->firstOrFail();
        $record = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
            ->where('task_type', 'record')
            ->firstOrFail();
        $this->completeWithEvidence($admin, $record);

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertViewHas('typeProgress', function ($types) {
                $byType = $types->keyBy('key');

                return $byType['record']['total'] === 3
                    && $byType['record']['completed'] === 1
                    && $byType['pickup']['total'] === 2
                    && $byType['pickup']['completed'] === 0
                    && $byType->keys()->all() === ['pickup', 'record', 'return', 'payment'];
            });
    }

    public function test_staff_filter_narrows_the_tracker_to_one_person(): void
    {
        $this->planScenarioWeek($this->admin());

        $admin = $this->admin();
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.weekly-bookkeeping.index', [
            'week_start' => self::WEEK,
            'staff' => $juan->id,
        ]));

        $response->assertOk();
        $this->assertSame(3, WeeklyBookkeepingTarget::where('assigned_staff_id', $juan->id)->count());
    }

    public function test_supervisor_can_reassign_a_task_and_it_is_recorded(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $supervisor = $this->supervisor();
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $clientC = User::query()->where('name', 'Client C')->firstOrFail();

        $target = WeeklyBookkeepingTarget::where('client_id', $clientC->id)->where('task_type', 'record')->firstOrFail();
        $this->assertSame($juan->id, $target->assigned_staff_id);

        $this->actingAs($supervisor)
            ->post(route('admin.weekly-bookkeeping.reassign-target', [$plan, $target]), [
                'assigned_staff_id' => $maria->id,
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame($maria->id, $target->assigned_staff_id);
        $this->assertSame('Maria Santos', $target->assignedStaffDisplayName());

        $this->assertTrue(
            $plan->history()->where('action', 'weekly_bookkeeping.target.reassigned')->exists(),
            'Reassignment must appear on the accountability trail.'
        );
    }

    public function test_plain_staff_cannot_reassign_work(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $clientC = User::query()->where('name', 'Client C')->firstOrFail();
        $target = WeeklyBookkeepingTarget::where('client_id', $clientC->id)->where('task_type', 'record')->firstOrFail();

        $this->actingAs($juan)
            ->post(route('admin.weekly-bookkeeping.reassign-target', [$plan, $target]), [
                'assigned_staff_id' => $maria->id,
            ])
            ->assertForbidden();

        $this->assertSame($juan->id, $target->fresh()->assigned_staff_id);
    }

    public function test_history_preserves_the_accountability_trail(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();
        $record = WeeklyBookkeepingTarget::where('client_id', $clientA->id)->where('task_type', 'record')->firstOrFail();

        $this->completeWithEvidence($juan, $record);

        $record = $record->fresh();

        $this->assertNotNull($record->started_at, 'Started timestamp is recorded.');
        $this->assertNotNull($record->ended_at, 'Completed timestamp is recorded.');
        $this->assertNotNull($record->durationInMinutes());
        $this->assertNotNull($record->attachment_path, 'Evidence is retained.');

        $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.history', $plan))
            ->assertOk()
            ->assertSee('Record')
            ->assertSee('assigned to Juan Dela Cruz')
            ->assertSee('Actual work started')
            ->assertSee('Actual work completed')
            ->assertSee('Juan Dela Cruz');
    }

    public function test_unpaid_follows_billing_instead_of_being_invented(): void
    {
        $this->planScenarioWeek($this->admin());
        $admin = $this->admin();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        $payment = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
            ->where('task_type', 'payment')
            ->firstOrFail();

        // No billing record yet: the client is still outstanding for this week.
        $this->assertTrue($payment->isUnpaid());
        $this->assertSame('Pending', $payment->cellLabel());

        // Billing records the money. Bookkeeping must not contradict it.
        Billing::create([
            'client_id' => $clientA->id,
            'period_label' => '3rd Quarter 2026',
            'quarter' => 3,
            'year' => 2026,
            'total' => 5000,
            'status' => Billing::STATUS_PAID,
            'paid_at' => '2026-09-29 14:00:00',
        ]);

        $payment = $payment->fresh();

        $this->assertFalse($payment->isUnpaid(), 'A paid billing removes the client from Unpaid.');
        $this->assertTrue($payment->isPaidViaBilling());
        $this->assertSame('Paid (Billing)', $payment->cellLabel());

        // Scope to payment tasks: the client's other tasks (pickup, record,
        // return) are legitimately still unpaid, so an unscoped "unpaid" view
        // would keep listing the client. Assert the grid empty state rather
        // than the client name, which assertDontSee() would also match inside
        // page chrome such as "client accounts".
        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', [
                'week_start' => self::WEEK,
                'view' => 'unpaid',
                'task_type' => 'payment',
            ]))
            ->assertOk()
            ->assertSee('No targets match this selection for the selected week.');
    }

    public function test_billing_for_a_later_period_does_not_mark_an_earlier_target_paid(): void
    {
        $this->planScenarioWeek($this->admin());
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        $payment = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
            ->where('task_type', 'payment')
            ->firstOrFail();

        Billing::create([
            'client_id' => $clientA->id,
            'period_label' => '4th Quarter 2026',
            'quarter' => 4,
            'year' => 2026,
            'total' => 5000,
            'status' => Billing::STATUS_PAID,
            'paid_at' => '2026-10-20 14:00:00',
        ]);

        $this->assertTrue($payment->fresh()->isUnpaid());
    }

    public function test_draft_billing_is_not_treated_as_paid(): void
    {
        $this->planScenarioWeek($this->admin());
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        $payment = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
            ->where('task_type', 'payment')
            ->firstOrFail();

        Billing::create([
            'client_id' => $clientA->id,
            'period_label' => '3rd Quarter 2026',
            'quarter' => 3,
            'year' => 2026,
            'total' => 5000,
            'status' => Billing::STATUS_DRAFT,
        ]);

        $this->assertTrue($payment->fresh()->isUnpaid());
    }

    public function test_plan_screen_does_not_leak_other_staff_work(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();
        $clientA = User::query()->where('name', 'Client A')->firstOrFail();

        $mariaPickup = WeeklyBookkeepingTarget::where('client_id', $clientA->id)
            ->where('task_type', 'pickup')
            ->firstOrFail();

        // Juan is legitimately on this plan, but only for Record work.
        $this->assertTrue($plan->isAssignedTo($juan));

        $response = $this->actingAs($juan)->get(route('admin.weekly-bookkeeping.show', $plan));
        $response->assertOk();
        $this->assertCount(3, $response->viewData('targets'));
        $this->assertTrue($response->viewData('targets')->every(fn ($t) => $t->isAssignedTo($juan)));

        $html = $response->getContent();
        $this->assertStringNotContainsString('target-'.$mariaPickup->id, $html);

        // Juan keeps his own actions but is offered no reassignment control.
        $this->assertTrue($response->viewData('canManage'));
        $this->assertFalse($response->viewData('canReassign'));

        // Admin oversight still sees the full plan.
        $this->assertCount(7, $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->viewData('targets'));
    }

    public function test_history_trail_is_scoped_for_plain_staff(): void
    {
        $plan = $this->planScenarioWeek($this->admin());
        $juan = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();

        $juanHistory = $this->actingAs($juan)
            ->get(route('admin.weekly-bookkeeping.history', $plan))
            ->assertOk()
            ->viewData('history');

        $adminHistory = $this->actingAs($this->admin())
            ->get(route('admin.weekly-bookkeeping.history', $plan))
            ->assertOk()
            ->viewData('history');

        $this->assertTrue($adminHistory->count() > $juanHistory->count());
    }

    public function test_printable_report_mirrors_the_comparison_sheet(): void
    {
        $this->planScenarioWeek($this->admin());
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.report', ['week_start' => self::WEEK]))
            ->assertOk()
            ->assertSee('Weekly Bookkeeping Report')
            ->assertSee('Sep 28, 2026')
            ->assertSee('Oct 4, 2026')
            ->assertSee('Client A')
            ->assertSee('Client B')
            ->assertSee('Client C')
            ->assertSee('Actual vs Target')
            ->assertSee('Uncollected / Unfinished')
            ->assertSee('Unpaid')
            ->assertSee('Print / Save as PDF');
    }

    public function test_report_respects_staff_scoping(): void
    {
        $this->planScenarioWeek($this->admin());
        $maria = User::query()->where('name', 'Maria Santos')->firstOrFail();

        $response = $this->actingAs($maria)
            ->get(route('admin.weekly-bookkeeping.report', ['week_start' => self::WEEK]));

        $response->assertOk();
        // Maria has no Record task, so no Record work may leak into her export.
        $this->assertSame(4, $response->viewData('rows')->count());
    }

    public function test_a_week_holds_a_single_plan_like_one_comparison_sheet(): void
    {
        $this->planScenarioWeek($this->admin());
        $supervisor = $this->supervisor();

        // A second planner adding to the same week extends the existing plan.
        $this->actingAs($supervisor)
            ->post(route('admin.weekly-bookkeeping.store'), [
                'week_start' => self::WEEK,
                'clients' => [User::query()->where('name', 'Client A')->firstOrFail()->id],
                'tasks' => [User::query()->where('name', 'Client A')->firstOrFail()->id => ['record']],
            ])
            ->assertRedirect();

        $this->assertSame(1, WeeklyBookkeeping::query()->whereDate('week_start', self::WEEK)->count());
    }
}
