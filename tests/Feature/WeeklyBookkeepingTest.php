<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use App\Models\WeeklyBookkeeping;
use App\Models\WeeklyBookkeepingTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WeeklyBookkeepingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('supabase');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function staff(string $name = 'Staff Member'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STAFF,
            'name' => $name,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function supervisor(string $name = 'Supervisor Member'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'name' => $name,
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
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function approvedClient(string $name = 'Test Client'): User
    {
        $client = $this->client($name);

        ClientSurveyResponse::create([
            'user_id' => $client->id,
            'submitted_at' => now(),
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => 'Test survey',
        ]);

        return $client;
    }

    private function weekStart(): string
    {
        return now()->startOfWeek()->format('Y-m-d');
    }

    /**
     * Creates a weekly plan. Each task is assigned its own staff member,
     * defaulting to the plan owner unless the client config names an
     * 'assignee' (a single staff id, or a per-task map).
     */
    private function createPlan(User $owner, string $week, array $clientsConfig, array $planOptions = []): void
    {
        $clients = [];
        $tasks = [];
        $dates = [];
        $assignee = [];

        foreach ($clientsConfig as $clientId => $cfg) {
            $clients[] = $clientId;
            $tasks[$clientId] = $cfg['tasks'];
            $dates[$clientId] = $cfg['date'] ?? null;

            $configured = $cfg['assignee'] ?? $owner->id;
            $map = [];
            foreach ($cfg['tasks'] as $taskType) {
                $map[$taskType] = is_array($configured) ? ($configured[$taskType] ?? null) : $configured;
            }
            $assignee[$clientId] = $map;
        }

        $this->actingAs($owner)
            ->post(route('admin.weekly-bookkeeping.store'), array_filter([
                'week_start' => $week,
                'clients' => $clients,
                'tasks' => $tasks,
                'target_date' => $dates,
                'assignee' => $assignee,
            ]) + $planOptions)
            ->assertRedirect();
    }

    private function findPlan(User $owner, string $week): WeeklyBookkeeping
    {
        return WeeklyBookkeeping::query()
            ->where('staff_id', $owner->id)
            ->whereDate('week_start', $week)
            ->firstOrFail();
    }

    public function test_staff_can_create_weekly_target_with_multiple_clients(): void
    {
        $staff = $this->staff('Maria Santos');
        $clientA = $this->client('Pedrigal, Marilou');
        $clientB = $this->client('Aguba, Samuel');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $clientA->id => ['tasks' => ['pickup'], 'date' => $week],
            $clientB->id => ['tasks' => ['record'], 'date' => $week],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->assertSame($staff->id, $plan->staff_id);
        $this->assertSame(2, $plan->targets()->count());
        $this->assertTrue($plan->targets()->where('client_id', $clientA->id)->where('task_type', 'pickup')->exists());
        $this->assertTrue($plan->targets()->where('client_id', $clientB->id)->where('task_type', 'record')->exists());
        $this->assertSame($week, $plan->targets()->where('client_id', $clientA->id)->first()->target_date->format('Y-m-d'));
    }

    public function test_a_client_can_have_multiple_target_tasks_in_the_same_week(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Villasin, Maricel');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['return', 'payment'], 'date' => $week],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->assertSame(2, $plan->targets()->count());
        $this->assertSame(1, $plan->targetClientCount());
        $this->assertTrue($plan->targets()->where('task_type', 'return')->exists());
        $this->assertTrue($plan->targets()->where('task_type', 'payment')->exists());
    }

    public function test_staff_can_save_all_targets_in_one_submission(): void
    {
        $staff = $this->staff('Maria Santos');
        $clients = collect([
            $this->client('Client A'),
            $this->client('Client B'),
            $this->client('Client C'),
            $this->client('Client D'),
            $this->client('Client E'),
        ]);
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $clients[0]->id => ['tasks' => ['pickup']],
            $clients[1]->id => ['tasks' => ['pickup']],
            $clients[2]->id => ['tasks' => ['record']],
            $clients[3]->id => ['tasks' => ['return']],
            $clients[4]->id => ['tasks' => ['return', 'payment']],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->assertSame(5, $plan->targetClientCount());
        $this->assertSame(6, $plan->targets()->count());
    }

    public function test_duplicate_target_for_same_client_and_task_is_not_duplicated(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);

        $plan = $this->findPlan($staff, $week);

        $this->assertSame(1, $plan->targets()->count());
    }

    public function test_staff_can_start_actual_work(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Pick-Up marked as in progress.');

        $target->refresh();
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS, $target->actual_status);
        $this->assertNotNull($target->started_at);
        $this->assertSame($staff->id, $target->performed_by_id);
        $this->assertSame('staff', $target->performed_by_role);
    }

    public function test_staff_cannot_access_another_staffs_plan(): void
    {
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staffA, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staffA, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staffB)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertForbidden();

        $this->actingAs($staffB)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertForbidden();
    }

    public function test_completion_requires_evidence(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]))
            ->assertSessionHasErrors('action');

        $target->refresh();
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS, $target->actual_status);
    }

    public function test_completion_with_evidence_records_accountability(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => [
            'tasks' => ['pickup'],
            // Targeted for today so the work below lands on the target date.
            'date' => now()->format('Y-m-d'),
        ]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]), [
                'attachment' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Pick-Up On Time.');

        $target->refresh();
        // Pick-Up resolves to "On Time" in the comparison workbook, not a
        // generic Completed.
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_ON_TIME, $target->actual_status);
        $this->assertTrue($target->isCompleted());
        $this->assertSame(WeeklyBookkeepingTarget::TIMING_ON_TIME, $target->timing);
        $this->assertNotNull($target->ended_at);
        $this->assertNotNull($target->attachment_path);
        $this->assertSame($staff->id, $target->performed_by_id);
        $this->assertSame($staff->name, $target->performed_by_name);

        $plan->refresh();
        $this->assertSame(WeeklyBookkeeping::STATUS_COMPLETED, $plan->status);
    }

    public function test_all_task_types_require_evidence_before_completion(): void
    {
        // A week holds a single plan (one comparison sheet), so each task type
        // is exercised in its own week.
        foreach (['pickup', 'record', 'return', 'payment'] as $index => $taskType) {
            $staff = $this->staff("Staff {$taskType}");
            $client = $this->client("Client {$taskType}");
            $week = now()->startOfWeek()->addWeeks($index)->format('Y-m-d');

            $this->createPlan($staff, $week, [$client->id => ['tasks' => [$taskType]]]);
            $plan = $this->findPlan($staff, $week);
            $target = $plan->targets()->first();

            $this->actingAs($staff)
                ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

            $this->actingAs($staff)
                ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]))
                ->assertSessionHasErrors('action');

            $target->refresh();
            $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS, $target->actual_status, "Task {$taskType} should require evidence");
        }
    }

    public function test_duration_is_calculated_correctly(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $target->update([
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(30),
            'ended_at' => now(),
        ]);

        $this->assertSame(30, $target->durationInMinutes());
        $this->assertStringContainsString('30 minute', $target->durationHuman());
    }

    public function test_supervisor_can_create_own_weekly_target(): void
    {
        $supervisor = $this->supervisor('Anna Supervisor');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($supervisor, $week, [$client->id => ['tasks' => ['record']]]);

        $plan = $this->findPlan($supervisor, $week);
        $this->assertSame(1, $plan->targets()->count());
    }

    public function test_supervisor_can_perform_staff_level_actual_work(): void
    {
        $supervisor = $this->supervisor('Anna Supervisor');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($supervisor, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($supervisor, $week);
        $target = $plan->targets()->first();

        $this->actingAs($supervisor)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('supervisor', $target->performed_by_role);
        $this->assertSame($supervisor->id, $target->performed_by_id);
    }

    public function test_supervisor_can_monitor_staff_targets(): void
    {
        $staff = $this->staff('Maria Santos');
        $supervisor = $this->supervisor('Anna Supervisor');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);

        $this->actingAs($supervisor)
            ->get(route('admin.weekly-bookkeeping.index'))
            ->assertOk()
            ->assertSee('Maria Santos');

        $this->actingAs($supervisor)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk();
    }

    public function test_admin_can_view_all_and_filter(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index'))
            ->assertOk()
            ->assertSee('Maria Santos');

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => $week, 'task_type' => 'pickup', 'status' => 'pending']))
            ->assertOk();
    }

    public function test_legacy_unowned_plan_still_renders(): void
    {
        $admin = $this->admin();
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $legacy = WeeklyBookkeeping::create([
            'staff_id' => null,
            'week_start' => $week,
            'week_end' => now()->startOfWeek()->endOfWeek()->format('Y-m-d'),
            'status' => WeeklyBookkeeping::STATUS_NOT_STARTED,
        ]);

        $legacy->targets()->create([
            'client_id' => $client->id,
            'task_type' => 'pickup',
            'target_date' => $week,
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.show', $legacy))
            ->assertOk()
            ->assertSee('—');
    }

    public function test_target_planner_uses_compact_task_controls(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Pedrigal, Marilou');
        $this->client('Aguba, Samuel');

        $response = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create'))
            ->assertOk();

        // Compact chip controls (not large buttons) for each task.
        $response->assertSee('class="task-chip"', false);
        $response->assertDontSee('class="btn task-check', false);

        // Bulk selection tooling and live counts are all still present.
        $response->assertSee('id="clientSearch"', false)
            ->assertSee('id="clientFilter"', false)
            ->assertSee('id="selectAllToggle"', false)
            ->assertSee('id="selectAllVisible"', false)
            ->assertSee('id="clearSelection"', false)
            ->assertSee('id="selectedCount"', false)
            ->assertSee('id="summaryClients"', false)
            ->assertSee('id="summaryTasks"', false)
            ->assertSee('Select all visible')
            ->assertSee('Clear selection')
            ->assertSee('Selected clients')
            ->assertSee('Planned targets')
            ->assertSee('Already saved');

        // Per-client target date input is still rendered for every task type.
        $response->assertSee('name="target_date['.$client->id.']"', false)
            ->assertSee('name="tasks['.$client->id.'][]"', false)
            ->assertSee('name="clients[]"', false);

        // Primary action + compact secondary action.
        $response->assertSee('Save Weekly Targets')
            ->assertSee('Back to tracker')
            ->assertSee('target-save', false);
    }

    public function test_target_planner_pages_render(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Pedrigal, Marilou');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup', 'record'], 'date' => $week]]);
        $plan = $this->findPlan($staff, $week);

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create'))
            ->assertOk()
            ->assertSee('Pedrigal, Marilou')
            ->assertSee('Pick-Up')
            ->assertSee('Return / Collect');

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $week]))
            ->assertOk()
            ->assertSee('Pedrigal, Marilou');

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->assertSee('Pedrigal, Marilou');

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.history', $plan))
            ->assertOk();
    }

    public function test_staff_index_is_scoped_to_own_plans(): void
    {
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staffA, $week, [$client->id => ['tasks' => ['pickup']]]);

        $this->actingAs($staffB)
            ->get(route('admin.weekly-bookkeeping.index'))
            ->assertOk()
            ->assertDontSee('Maria Santos');
    }

    public function test_client_cannot_access_weekly_bookkeeping(): void
    {
        $client = $this->approvedClient('Client A');

        $this->actingAs($client)
            ->get(route('admin.weekly-bookkeeping.index'))
            ->assertForbidden();
    }

    public function test_client_does_not_see_sidebar_item(): void
    {
        $client = $this->approvedClient('Client A');

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertDontSee('Weekly Bookkeeping');
    }

    public function test_actual_performer_is_separate_from_target_owner(): void
    {
        $staff = $this->staff('Maria Santos');
        $supervisor = $this->supervisor('Juan Dela Cruz');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($supervisor)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect();

        $file = UploadedFile::fake()->image('proof.jpg');
        $this->actingAs($supervisor)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]), [
                'attachment' => $file,
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame($staff->id, $plan->staff_id);
        $this->assertSame($supervisor->id, $target->performed_by_id);
        $this->assertSame('Juan Dela Cruz', $target->performed_by_name);
        $this->assertSame('supervisor', $target->performed_by_role);
    }

    public function test_history_is_recorded_for_target_lifecycle(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Pedrigal, Marilou');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

        $file = UploadedFile::fake()->image('proof.jpg');
        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]), [
                'attachment' => $file,
            ]);

        $this->assertTrue(ActivityLog::query()->where('weekly_bookkeeping_id', $plan->id)->where('action', 'weekly_bookkeeping.target_added')->exists());
        $this->assertTrue(ActivityLog::query()->where('weekly_bookkeeping_id', $plan->id)->where('action', 'weekly_bookkeeping.target.started')->exists());
        $this->assertTrue(ActivityLog::query()->where('weekly_bookkeeping_id', $plan->id)->where('action', 'weekly_bookkeeping.target.completed')->exists());

        $completedLog = ActivityLog::query()->where('weekly_bookkeeping_id', $plan->id)->where('action', 'weekly_bookkeeping.target.completed')->first();
        $this->assertStringContainsString('Maria Santos', $completedLog->description);
        $this->assertStringContainsString('proof.jpg', $completedLog->description);
    }

    public function test_target_can_be_edited_before_work_starts(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();
        $newDate = now()->startOfWeek()->addDay()->format('Y-m-d');

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $target]), [
                'target_date' => $newDate,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $target->refresh();
        $this->assertSame($newDate, $target->target_date->format('Y-m-d'));
    }

    public function test_target_cannot_be_edited_after_work_starts(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $target]), [
                'target_date' => now()->startOfWeek()->addDay()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('action');
    }

    public function test_the_task_sequence_offsets_run_pickup_record_return_payment(): void
    {
        $this->assertSame(
            ['pickup' => 0, 'record' => 1, 'return' => 2, 'payment' => 3],
            WeeklyBookkeepingTarget::SEQUENCE_OFFSETS
        );
    }

    public function test_changing_the_pickup_date_reschedules_the_later_stages(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record', 'return', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);
        $newPickup = $this->insideWeek(2);

        /* Seed the later stages as suggestions, which is what the picker does. */
        foreach (['record', 'return', 'payment'] as $taskType) {
            $this->targetFor($plan, $client->id, $taskType)->update([
                'target_date_auto' => true,
                'target_date' => $this->insideWeek(3),
            ]);
        }

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'pickup'),
            ]), ['target_date' => $newPickup])
            ->assertRedirect();

        $this->assertSame($this->insideWeek(3), $this->targetFor($plan, $client->id, 'record')->target_date->format('Y-m-d'));
        $this->assertSame($this->insideWeek(4), $this->targetFor($plan, $client->id, 'return')->target_date->format('Y-m-d'));
        $this->assertSame($this->insideWeek(5), $this->targetFor($plan, $client->id, 'payment')->target_date->format('Y-m-d'));
    }

    public function test_a_reschedule_leaves_manually_edited_dates_alone(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record', 'return', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);

        /* Record was moved by hand, so it is no longer a suggestion. */
        $this->targetFor($plan, $client->id, 'record')->update([
            'target_date_auto' => false,
            'target_date' => $this->insideWeek(5),
        ]);

        $this->targetFor($plan, $client->id, 'return')->update(['target_date_auto' => true]);
        $this->targetFor($plan, $client->id, 'payment')->update(['target_date_auto' => true]);

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'pickup'),
            ]), ['target_date' => $this->insideWeek(2)]);

        $this->assertSame(
            $this->insideWeek(5),
            $this->targetFor($plan, $client->id, 'record')->target_date->format('Y-m-d'),
            'A hand-edited Record date must survive a Pick-Up change.'
        );

        $this->assertSame($this->insideWeek(4), $this->targetFor($plan, $client->id, 'return')->target_date->format('Y-m-d'));
        $this->assertSame($this->insideWeek(5), $this->targetFor($plan, $client->id, 'payment')->target_date->format('Y-m-d'));
    }

    public function test_editing_a_date_by_hand_stops_it_following_the_sequence(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->targetFor($plan, $client->id, 'record')->update(['target_date_auto' => true]);

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'record'),
            ]), ['target_date' => $this->insideWeek(6)]);

        $record = $this->targetFor($plan, $client->id, 'record');

        $this->assertSame($this->insideWeek(6), $record->target_date->format('Y-m-d'));
        $this->assertFalse($record->dateIsAutomatic());

        /* And a later Pick-Up change must no longer drag it along. */
        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'pickup'),
            ]), ['target_date' => $this->insideWeek(2)]);

        $this->assertSame($this->insideWeek(6), $record->fresh()->target_date->format('Y-m-d'));
    }

    public function test_a_reschedule_does_not_move_a_stage_that_already_has_work_recorded(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);
        $record = $this->targetFor($plan, $client->id, 'record');

        $this->actingAs($staff)->post(route('admin.weekly-bookkeeping.start-target', [$plan, $record]));

        $record->refresh();
        $record->update(['target_date_auto' => true]);
        $originalDate = $record->target_date->format('Y-m-d');

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'pickup'),
            ]), ['target_date' => $this->insideWeek(2)]);

        $this->assertSame($originalDate, $record->fresh()->target_date->format('Y-m-d'));
    }

    public function test_a_reschedule_does_not_reach_a_stage_belonging_to_another_staff_member(): void
    {
        $staff = $this->staff('Maria Santos');
        $colleague = $this->staff('Other Bookkeeper');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => [
                'tasks' => ['pickup', 'record'],
                'date' => $this->insideWeek(1),
                'assignee' => ['pickup' => $staff->id, 'record' => $colleague->id],
            ],
        ]);

        $plan = $this->findPlan($staff, $week);
        $record = $this->targetFor($plan, $client->id, 'record');

        $record->update(['target_date_auto' => true, 'target_date' => $this->insideWeek(3)]);

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'pickup'),
            ]), ['target_date' => $this->insideWeek(2)]);

        $this->assertSame(
            $this->insideWeek(3),
            $record->fresh()->target_date->format('Y-m-d'),
            'A Pick-Up edit must not silently reschedule a colleague’s task.'
        );
    }

    public function test_a_supervisor_reschedule_covers_every_stage(): void
    {
        $staff = $this->staff('Maria Santos');
        $supervisor = $this->supervisor('Oversight');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => [
                'tasks' => ['pickup', 'record'],
                'date' => $this->insideWeek(1),
                'assignee' => ['pickup' => $staff->id, 'record' => $staff->id],
            ],
        ]);

        $plan = $this->findPlan($staff, $week);
        $this->targetFor($plan, $client->id, 'record')->update([
            'target_date_auto' => true,
            'target_date' => $this->insideWeek(3),
        ]);

        $this->actingAs($supervisor)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'pickup'),
            ]), ['target_date' => $this->insideWeek(2)]);

        $this->assertSame($this->insideWeek(3), $this->targetFor($plan, $client->id, 'record')->target_date->format('Y-m-d'));
    }

    public function test_suggested_dates_are_clamped_to_the_last_day_of_the_week(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);

        $plan = $this->findPlan($staff, $week);
        $sunday = $this->insideWeek(6);

        /* Pick-Up on the final day leaves no room inside the week, so every later
           stage folds back onto the Sunday rather than escaping the plan. */
        $this->assertSame($sunday, $this->targetFor($plan, $client->id, 'pickup')
            ->suggestedDateFor('payment', $sunday)
            ->format('Y-m-d'));

        $this->assertSame($sunday, $this->targetFor($plan, $client->id, 'pickup')
            ->suggestedDateFor('record', $sunday)
            ->format('Y-m-d'));
    }

    public function test_remarks_and_a_balance_can_be_saved_on_a_target(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'payment'),
            ]), [
                'notes' => 'Client will settle next week.',
                'payment_status' => WeeklyBookkeepingTarget::PAYMENT_STATUS_WITH_BALANCE,
                'balance_amount' => '2500.00',
                'balance_note' => 'Remaining half of the April retainer.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $payment = $this->targetFor($plan, $client->id, 'payment');

        $this->assertSame('Client will settle next week.', $payment->notes);
        $this->assertSame('with_balance', $payment->payment_status);
        $this->assertSame('2500.00', $payment->balance_amount);
        $this->assertSame('Remaining half of the April retainer.', $payment->balance_note);
        $this->assertSame('With Balance · ₱2,500.00', $payment->balanceSummary());
    }

    public function test_the_balance_is_cleared_once_the_status_is_no_longer_with_balance(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->targetFor($plan, $client->id, 'payment')->update([
            'payment_status' => 'with_balance',
            'balance_amount' => 2500,
            'balance_note' => 'Part payment.',
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'payment'),
            ]), ['payment_status' => WeeklyBookkeepingTarget::PAYMENT_STATUS_PAID]);

        $payment = $this->targetFor($plan, $client->id, 'payment');

        $this->assertSame('paid', $payment->payment_status);
        $this->assertNull($payment->balance_amount);
        $this->assertNull($payment->balance_note);
        $this->assertFalse($payment->hasBalance());
    }

    public function test_a_balance_amount_is_required_and_must_not_be_negative(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);
        $route = fn () => route('admin.weekly-bookkeeping.update-target', [
            $plan, $this->targetFor($plan, $client->id, 'payment'),
        ]);

        $this->actingAs($staff)
            ->patch($route(), ['payment_status' => 'with_balance'])
            ->assertSessionHasErrors('balance_amount');

        $this->actingAs($staff)
            ->patch($route(), ['payment_status' => 'with_balance', 'balance_amount' => '-5'])
            ->assertSessionHasErrors('balance_amount');
    }

    public function test_a_balance_cannot_be_set_without_the_payment_status_choice(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'payment'),
            ]), ['payment_status' => 'invented_status'])
            ->assertSessionHasErrors('payment_status');
    }

    public function test_a_legacy_target_without_the_auto_flag_is_never_rescheduled(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);
        $record = $this->targetFor($plan, $client->id, 'record');

        /* A row that predates the feature carries a NULL flag. */
        DB::table('weekly_bookkeeping_targets')
            ->where('id', $record->id)
            ->update(['target_date_auto' => null, 'target_date' => $this->insideWeek(3)]);

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [
                $plan, $this->targetFor($plan, $client->id, 'pickup'),
            ]), ['target_date' => $this->insideWeek(2)]);

        $this->assertSame($this->insideWeek(3), $record->fresh()->target_date->format('Y-m-d'));
    }

    public function test_recording_a_balance_does_not_change_the_paid_or_unpaid_roll_up(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);
        $payment = $this->targetFor($plan, $client->id, 'payment');

        $this->assertTrue($payment->isUnpaid(), 'Billing stays authoritative while nothing has been recorded.');

        $payment->update([
            'payment_status' => 'with_balance',
            'balance_amount' => 2500,
        ]);

        $this->assertTrue(
            $payment->fresh()->isUnpaid(),
            'A part payment is not a settled fee, so the Unpaid roll-up is unchanged.'
        );
    }

    public function test_the_plan_page_shows_the_sequence_with_an_editable_stage_form(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record', 'return', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->assertSee('Target Schedule')
            ->assertSee('Pick-Up sets the sequence')
            ->assertSee('+1 day from Pick-Up', false)
            ->assertSee('+3 days from Pick-Up', false)
            /* One editable date, remarks and the balance fields per stage. */
            ->assertSee('name="target_date"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('name="payment_status"', false)
            ->assertSee('name="balance_amount"', false)
            ->assertSee('name="balance_note"', false);
    }

    public function test_the_plan_page_shows_a_recorded_balance_and_remarks(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);

        $this->targetFor($plan, $client->id, 'payment')->update([
            'notes' => 'Half received this week.',
            'payment_status' => 'with_balance',
            'balance_amount' => 1250,
            'balance_note' => 'Rest due after the BIR filing.',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->assertSee('With Balance · ₱1,250.00')
            ->assertSee('Rest due after the BIR filing.')
            ->assertSee('Half received this week.');

        /* And the tracker row carries it without gaining a column. */
        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.index'))
            ->assertOk()
            ->assertSee('With Balance · ₱1,250.00');
    }

    public function test_the_assigned_staff_can_add_remarks_once_the_work_has_started(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);

        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect();

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $target]), [
                'notes' => 'Books were incomplete; client is sending the rest tomorrow.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $target->refresh();
        $this->assertSame('Books were incomplete; client is sending the rest tomorrow.', $target->notes);
    }

    public function test_a_remarks_only_save_does_not_touch_the_other_target_fields(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);
        $payment = $this->targetFor($plan, $client->id, 'payment');

        $payment->update([
            'notes' => 'Awaiting the BIR receipt.',
            'payment_status' => WeeklyBookkeepingTarget::PAYMENT_STATUS_WITH_BALANCE,
            'balance_amount' => '2500.00',
            'balance_note' => 'Remaining half of the retainer.',
        ]);

        $dateBefore = $payment->target_date->format('Y-m-d');
        $sequenceBefore = $payment->sequence;

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $payment]))
            ->assertRedirect();

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $payment]), [
                'notes' => 'Client called: payment lands Friday.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $payment->refresh();
        $this->assertSame('Client called: payment lands Friday.', $payment->notes);
        $this->assertSame($dateBefore, $payment->target_date->format('Y-m-d'));
        $this->assertSame($sequenceBefore, $payment->sequence);
        $this->assertSame($staff->id, $payment->assigned_staff_id);
        $this->assertSame('with_balance', $payment->payment_status);
        $this->assertSame('2500.00', $payment->balance_amount);
        $this->assertSame('Remaining half of the retainer.', $payment->balance_note);
    }

    public function test_a_remarks_only_save_does_not_blank_existing_remarks(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);

        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $target->update(['notes' => 'Keep me.']);

        /* A date-only save (the old reschedule form) must not wipe the remark. */
        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $target]), [
                'target_date' => $this->insideWeek(2),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $this->assertSame('Keep me.', $target->refresh()->notes);
    }

    public function test_remarks_cannot_be_edited_once_the_task_is_completed(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup'], 'date' => now()->format('Y-m-d')],
        ]);

        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect();

        // Pick-Up only completes once its proof of work is on file.
        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->isCompleted());

        $this->actingAs($staff)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $target]), [
                'notes' => 'Trying to rewrite history.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('action');

        $this->assertNull($target->refresh()->notes);
    }

    public function test_remarks_cannot_be_edited_by_another_staff_member(): void
    {
        $owner = $this->staff('Maria Santos');
        $other = $this->staff('Other Staff');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($owner, $week, [$client->id => ['tasks' => ['pickup']]]);

        $plan = $this->findPlan($owner, $week);
        $target = $plan->targets()->first();

        $this->actingAs($owner)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect();

        $this->actingAs($other)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $target]), [
                'notes' => 'Not my task.',
            ])
            ->assertForbidden();

        $this->assertNull($target->refresh()->notes);
    }

    public function test_a_supervisor_can_add_remarks_to_a_started_task(): void
    {
        $staff = $this->staff('Maria Santos');
        $supervisor = $this->supervisor('Juan Dela Cruz');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);

        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect();

        $this->actingAs($supervisor)
            ->patch(route('admin.weekly-bookkeeping.update-target', [$plan, $target]), [
                'notes' => 'Oversight note after a spot check.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $this->assertSame('Oversight note after a spot check.', $target->refresh()->notes);
    }

    public function test_the_plan_page_offers_remarks_editing_only_where_it_is_allowed(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup'], 'date' => now()->format('Y-m-d')],
        ]);

        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        /* Pending: the assigned staff member may still start the remarks. */
        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->assertSee('data-bs-target="#bkRemarksModal"', false);

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]))
            ->assertRedirect();

        /* In progress: the remark is still editable. */
        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->assertSee('data-bs-target="#bkRemarksModal"', false);

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect();

        /* Completed: read-only, so no editor is offered any more. The modal
           itself stays on the page for the rows that can still be edited, but
           nothing triggers it for this task. */
        $html = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'data-bk-remarks-for="'.$target->id.'"',
            $html,
            'A completed task should not offer a remarks editor.'
        );
    }

    public function test_the_remarks_editor_submits_only_the_remarks(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        /* A payment stage, because that is the stage whose form also carries the
           balance and payment fields the remarks editor must never touch. */
        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(1)],
        ]);

        $plan = $this->findPlan($staff, $week);
        $payment = $this->targetFor($plan, $client->id, 'payment');

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $payment]))
            ->assertRedirect();

        $html = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->getContent();

        /* The remarks modal is rendered last on the page, so everything from its
           id onwards is the editor. It carries the remarks field and nothing that
           could move a date, a payment, a balance or an assignment. */
        $start = strpos($html, 'id="bkRemarksModal"');
        $this->assertNotFalse($start, 'The remarks modal should be present on the plan page.');

        $editor = substr($html, $start);

        $this->assertStringContainsString('name="notes"', $editor);
        $this->assertStringNotContainsString('target_date', $editor);
        $this->assertStringNotContainsString('payment_status', $editor);
        $this->assertStringNotContainsString('balance_amount', $editor);
        $this->assertStringNotContainsString('balance_note', $editor);
        $this->assertStringNotContainsString('assigned_staff_id', $editor);
    }

    public function test_the_balance_fields_stay_hidden_on_a_stage_that_is_not_payment(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);

        $plan = $this->findPlan($staff, $week);

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->assertSee('name="target_date"', false)
            ->assertDontSee('name="payment_status"', false);
    }

    public function test_pending_target_can_be_removed_and_history_recorded(): void
    {
        $staff = $this->staff('Maria Santos');
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $clientA->id => ['tasks' => ['pickup']],
            $clientB->id => ['tasks' => ['record']],
        ]);
        $plan = $this->findPlan($staff, $week);
        $this->assertSame(2, $plan->targets()->count());

        $this->createPlan($staff, $week, [
            $clientA->id => ['tasks' => ['pickup']],
        ]);

        $plan->refresh();
        $this->assertSame(1, $plan->targets()->count());
        $this->assertTrue($plan->targets()->where('client_id', $clientA->id)->exists());
        $this->assertTrue(ActivityLog::query()->where('weekly_bookkeeping_id', $plan->id)->where('action', 'weekly_bookkeeping.target_removed')->exists());
    }

    public function test_missed_task_shows_past_due_status_after_week(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = now()->subWeeks(2)->startOfWeek()->format('Y-m-d');

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->assertTrue($target->isPastDue());
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_MISSED, $target->effectiveStatus());
        $this->assertSame('Missed', $target->effectiveStatusLabel());
    }

    public function test_unreturned_and_unpaid_statuses_derive_correctly(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = now()->subWeeks(2)->startOfWeek()->format('Y-m-d');

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['return', 'payment']],
        ]);
        $plan = $this->findPlan($staff, $week);

        $this->assertSame('Unreturned', $plan->targets()->where('task_type', 'return')->first()->effectiveStatusLabel());
        $this->assertSame('Unpaid', $plan->targets()->where('task_type', 'payment')->first()->effectiveStatusLabel());
    }

    public function test_week_filtering_works(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week1 = now()->subWeeks(2)->startOfWeek()->format('Y-m-d');
        $week2 = $this->weekStart();

        $this->createPlan($staff, $week1, [$client->id => ['tasks' => ['pickup']]]);
        $this->createPlan($staff, $week2, [$client->id => ['tasks' => ['record']]]);

        $plan1 = $this->findPlan($staff, $week1);
        $plan2 = $this->findPlan($staff, $week2);

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => $week2]))
            ->assertOk()
            ->assertSee(route('admin.weekly-bookkeeping.show', $plan2))
            ->assertDontSee(route('admin.weekly-bookkeeping.show', $plan1));

        $this->actingAs($admin)
            ->get(route('admin.weekly-bookkeeping.index', ['week_start' => $week1]))
            ->assertOk()
            ->assertSee(route('admin.weekly-bookkeeping.show', $plan1))
            ->assertDontSee(route('admin.weekly-bookkeeping.show', $plan2));
    }

    public function test_admin_can_change_target_owner(): void
    {
        $admin = $this->admin();
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staffA, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staffA, $week);

        $this->actingAs($admin)
            ->post(route('admin.weekly-bookkeeping.update-owner', $plan), [
                'staff_id' => $staffB->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target owner updated.');

        $plan->refresh();
        $this->assertSame($staffB->id, $plan->staff_id);
        $this->assertTrue(ActivityLog::query()->where('weekly_bookkeeping_id', $plan->id)->where('action', 'weekly_bookkeeping.owner_updated')->exists());
    }

    public function test_staff_cannot_change_target_owner(): void
    {
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staffA, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staffA, $week);

        $this->actingAs($staffB)
            ->post(route('admin.weekly-bookkeeping.update-owner', $plan), [
                'staff_id' => $staffB->id,
            ])
            ->assertForbidden();
    }

    public function test_only_admin_can_delete_plan(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);

        $this->actingAs($staff)
            ->delete(route('admin.weekly-bookkeeping.destroy', $plan))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.weekly-bookkeeping.destroy', $plan))
            ->assertRedirect();

        $this->assertDatabaseMissing('weekly_bookkeeping', ['id' => $plan->id]);
    }

    public function test_evidence_upload_records_history(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['record']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

        $file = UploadedFile::fake()->create('output.pdf', 1024);

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.upload-attachment', [$plan, $target]), [
                'attachment' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Evidence uploaded.');

        $target->refresh();
        $this->assertNotNull($target->attachment_path);
        $this->assertSame('output.pdf', $target->attachment_name);

        $this->assertTrue(ActivityLog::query()->where('weekly_bookkeeping_id', $plan->id)->where('action', 'weekly_bookkeeping.attachment_uploaded')->exists());
    }

    private function planWithEvidence(User $owner, string $taskType = 'pickup'): array
    {
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($owner, $week, [$client->id => ['tasks' => [$taskType]]]);
        $plan = $this->findPlan($owner, $week);
        $target = $plan->targets()->first();

        $this->actingAs($owner)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

        $this->actingAs($owner)
            ->post(route('admin.weekly-bookkeeping.upload-attachment', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect();

        return [$plan->refresh(), $target->refresh()];
    }

    private function stubSignedUrls(): void
    {
        Storage::disk('supabase')->buildTemporaryUrlsUsing(
            fn (string $path) => 'https://signed.example.test/'.ltrim($path, '/')
        );
    }

    public function test_evidence_view_redirects_to_signed_url_instead_of_erroring(): void
    {
        $staff = $this->staff('Maria Santos');
        [$plan, $target] = $this->planWithEvidence($staff);

        $this->stubSignedUrls();

        $response = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.view-attachment', [$plan, $target]));

        $response->assertRedirect();
        $response->assertHeader('Pragma', 'no-cache');

        // Symfony's ResponseHeaderBag normalises and re-sorts cache-control
        // directives, so assert the directives rather than the raw string.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }

        $this->assertStringStartsWith('https://signed.example.test/', (string) $response->headers->get('Location'));
    }

    public function test_evidence_download_returns_the_file(): void
    {
        $staff = $this->staff('Maria Santos');
        [$plan, $target] = $this->planWithEvidence($staff);

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.download-attachment', [$plan, $target]))
            ->assertOk();
    }

    public function test_viewing_evidence_without_attachment_returns_404(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.view-attachment', [$plan, $target]))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.download-attachment', [$plan, $target]))
            ->assertNotFound();
    }

    public function test_unauthorized_user_cannot_view_or_download_evidence(): void
    {
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        [$plan, $target] = $this->planWithEvidence($staffA);

        $this->stubSignedUrls();

        $this->actingAs($staffB)
            ->get(route('admin.weekly-bookkeeping.view-attachment', [$plan, $target]))
            ->assertForbidden();

        $this->actingAs($staffB)
            ->get(route('admin.weekly-bookkeeping.download-attachment', [$plan, $target]))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Planner: single picker, defaults, summary
    // -----------------------------------------------------------------

    /** A date inside the week but not on its first day. */
    private function insideWeek(int $daysIn): string
    {
        return now()->startOfWeek()->addDays($daysIn)->format('Y-m-d');
    }

    /** A date just outside the week, used to prove server-side range checks. */
    private function outsideWeek(): string
    {
        return now()->startOfWeek()->subDay()->format('Y-m-d');
    }

    private function targetFor(WeeklyBookkeeping $plan, int $clientId, string $taskType): WeeklyBookkeepingTarget
    {
        return $plan->targets()
            ->where('client_id', $clientId)
            ->where('task_type', $taskType)
            ->firstOrFail();
    }

    public function test_the_planner_rejects_a_target_date_outside_the_week(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.store'), [
                'week_start' => $this->weekStart(),
                'clients' => [$client->id],
                'tasks' => [$client->id => ['pickup']],
                'target_date' => [$client->id => $this->outsideWeek()],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('target_date.'.$client->id);

        /* Dates are resolved before anything is written, so a rejected date must
           not leave a half-built week behind. */
        $this->assertSame(0, WeeklyBookkeeping::query()->count());
        $this->assertSame(0, WeeklyBookkeepingTarget::query()->count());
    }

    public function test_existing_plan_records_still_render_on_the_planner(): void
    {
        $staff = $this->staff('Angeli');
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');

        $this->createPlan($staff, $this->weekStart(), [
            $clientA->id => ['tasks' => ['pickup', 'payment'], 'date' => $this->insideWeek(2)],
            $clientB->id => ['tasks' => ['record', 'payment'], 'date' => $this->insideWeek(4)],
        ]);

        $plan = $this->findPlan($staff, $this->weekStart());
        $this->assertSame(4, $plan->targets()->count());

        /* Existing work is summarised in the app's own table style, one row per
           staff + date + task group. */
        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $this->weekStart()]))
            ->assertOk()
            ->assertSee('Client A')
            ->assertSee('Client B')
            ->assertSee('Current Assignments')
            ->assertSee('<th>Staff</th>', false)
            ->assertSee('<th>Target Date</th>', false)
            ->assertSee('<th>Task</th>', false)
            ->assertSee('<th>Clients</th>', false)
            ->assertSee('<th>Status</th>', false)
            ->assertSee('Angeli')
            ->assertSee('Payment');
    }

    public function test_the_planner_has_a_single_client_picker_and_no_bulk_assignment_panel(): void
    {
        $staff = $this->staff('Angeli');
        $this->client('Client A');

        $html = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $this->weekStart()]))
            ->assertOk()
            ->assertDontSee('id="assignForm"')
            ->assertDontSee('Assign Bookkeeping Tasks')
            ->assertDontSee('Add Assignment')
            ->assertDontSee('name="assigned_staff_id"', false)
            ->assertDontSee('admin.weekly-bookkeeping.bulk-assign')
            ->assertSee('id="targetForm"', false)
            ->getContent();

        /* Exactly one client list posts the plan. */
        $this->assertSame(1, substr_count($html, 'name="clients[]"'));
        $this->assertStringNotContainsString('<details class="target-adv">', $html);
    }

    public function test_completion_percent_counts_task_specific_outcomes_not_just_the_word_completed(): void
    {
        $staff = $this->staff('Maria Santos');
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $clientA->id => ['tasks' => ['pickup'], 'date' => now()->format('Y-m-d')],
            $clientB->id => ['tasks' => ['record']],
        ]);
        $plan = $this->findPlan($staff, $week);

        $this->assertSame(0, $plan->fresh()->completionPercent());

        $pickup = $this->targetFor($plan, $clientA->id, 'pickup');

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $pickup]));

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $pickup]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Pick-Up On Time.');

        $pickup->refresh();
        /* A finished Pick-Up reads "On Time", not "Completed". Counting only the
           literal "completed" status left this plan at 0% forever. */
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_ON_TIME, $pickup->actual_status);
        $this->assertSame(50, $plan->fresh()->completionPercent());

        /* The show page renders the very same figure. */
        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->assertSee('<div class="info-value">50%</div>', false);
    }

    public function test_a_disallowed_evidence_file_type_returns_a_validation_error_not_a_crash(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);
        $target = $plan->targets()->first();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));

        /* A disallowed file used to escape as an InvalidArgumentException and
           surface as a 500 page. It must come back as a field-level validation
           error so the user can simply pick another file. */
        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.upload-attachment', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->create('payload.exe', 64),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('attachment');

        $target->refresh();
        $this->assertNull($target->attachment_path);
        $this->assertNull($target->attachment_name);
    }

    public function test_a_failed_target_save_rechecks_the_previously_selected_clients(): void
    {
        $staff = $this->staff('Angeli');
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $week = $this->weekStart();

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.store'), [
                'week_start' => $week,
                'clients' => [$clientA->id, $clientB->id],
                'tasks' => [
                    $clientA->id => ['pickup'],
                    $clientB->id => ['pickup'],
                ],
                // Outside the week: fails validation and flashes old input.
                'target_date' => [
                    $clientA->id => $this->outsideWeek(),
                    $clientB->id => $this->outsideWeek(),
                ],
            ])
            ->assertSessionHasErrors('target_date.'.$clientA->id);

        $html = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $week]))
            ->assertOk()
            ->getContent();

        /* Both clients are ticked again after the bounce, so a validation error
           never silently empties the selection the user was building. */
        foreach ([$clientA, $clientB] as $client) {
            $this->assertMatchesRegularExpression(
                '/name="clients\[\]" value="'.$client->id.'"[^>]*checked/',
                $html
            );
        }

        /* On a page without flashed input nobody is ticked. */
        $fresh = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $week]))
            ->assertOk()
            ->getContent();

        foreach ([$clientA, $clientB] as $client) {
            $this->assertDoesNotMatchRegularExpression(
                '/name="clients\[\]" value="'.$client->id.'"[^>]*checked/',
                $fresh
            );
        }
    }

    public function test_newly_selected_clients_default_to_pickup_return_and_payment(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $this->weekStart()]))
            ->assertOk()
            ->assertSee("var DEFAULT_TASKS = ['pickup', 'return', 'payment'];", false);

        /* Each row also renders the saved task set it restores on re-tick, so a
           client with existing targets is never re-defaulted from scratch. */
        $this->createPlan($staff, $this->weekStart(), [
            $client->id => ['tasks' => ['record'], 'date' => $this->insideWeek(2)],
        ]);

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $this->weekStart()]))
            ->assertOk()
            ->assertSee('data-existing="record"', false);
    }

    public function test_the_summary_counts_saved_targets_separately_from_new_ones(): void
    {
        $staff = $this->staff('Angeli');
        $this->client('Client A');

        $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.create', ['week_start' => $this->weekStart()]))
            ->assertOk()
            ->assertSee('Selected clients')
            ->assertSee('Planned targets')
            ->assertSee('Already saved')
            ->assertSee('id="summaryClients"', false)
            ->assertSee('id="summaryTasks"', false)
            ->assertSee('id="summarySaved"', false)
            ->assertSee('id="summaryRemovals"', false);
    }

    // -----------------------------------------------------------------
    // Automatic Pick-Up → Record → Return → Billing transitions
    // -----------------------------------------------------------------

    public function test_completing_pickup_auto_starts_the_next_record_stage(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record', 'return', 'payment']],
        ]);
        $plan = $this->findPlan($staff, $week);

        $pickup = $this->targetFor($plan, $client->id, 'pickup');
        $record = $this->targetFor($plan, $client->id, 'record');

        $this->assertTrue($record->isPending());

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $pickup]));

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $pickup]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect();

        $record->refresh();
        /* Finishing Pick-Up must move Record to In Progress, not complete it. */
        $this->assertTrue($record->isInProgress(), 'Record should auto-start after Pick-Up completes.');
        $this->assertNotNull($record->started_at);
        $this->assertFalse($record->isCompleted(), 'Record must not be auto-completed.');
    }

    public function test_the_whole_sequence_advances_one_stage_at_a_time(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record', 'return', 'payment']],
        ]);
        $plan = $this->findPlan($staff, $week);

        $sequence = ['pickup', 'record', 'return', 'payment'];

        foreach ($sequence as $index => $type) {
            $target = $this->targetFor($plan, $client->id, $type);

            /* Only the first stage starts pending; each later one should have
               been auto-started by the previous completion. */
            if ($index > 0) {
                $this->assertTrue($target->isInProgress(), "{$type} should be in progress.");
            }

            if ($target->isPending()) {
                $this->actingAs($staff)
                    ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $target]));
            }

            $this->actingAs($staff)
                ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $target]), [
                    'attachment' => UploadedFile::fake()->image("proof-{$type}.jpg"),
                ])
                ->assertRedirect();
        }

        foreach ($sequence as $type) {
            $this->assertTrue(
                $this->targetFor($plan, $client->id, $type)->isCompleted(),
                "{$type} should be completed."
            );
        }
    }

    public function test_completing_the_final_payment_stage_starts_nothing_and_does_not_reset_earlier_work(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'payment']],
        ]);
        $plan = $this->findPlan($staff, $week);

        $pickup = $this->targetFor($plan, $client->id, 'pickup');
        $payment = $this->targetFor($plan, $client->id, 'payment');

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $pickup]));
        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $pickup]), [
                'attachment' => UploadedFile::fake()->image('pickup.jpg'),
            ])
            ->assertRedirect();

        /* Record is absent from the plan, so the auto-advance finds no next
           stage and must not error or invent one. */
        $this->assertSame(2, $plan->targets()->count());

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $payment]));
        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $payment]), [
                'attachment' => UploadedFile::fake()->image('payment.jpg'),
            ])
            ->assertRedirect();

        /* The final stage completes without touching the already-finished
           Pick-Up. */
        $pickup->refresh();
        $this->assertTrue($pickup->isCompleted());
        $this->assertSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_ON_TIME, $pickup->actual_status);
    }

    public function test_auto_advance_is_idempotent_and_never_resets_a_started_stage(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [
            $client->id => ['tasks' => ['pickup', 'record']],
        ]);
        $plan = $this->findPlan($staff, $week);

        $pickup = $this->targetFor($plan, $client->id, 'pickup');
        $record = $this->targetFor($plan, $client->id, 'record');

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $record]));
        $record->refresh();
        $firstStartedAt = $record->started_at;

        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.start-target', [$plan, $pickup]));
        $this->actingAs($staff)
            ->post(route('admin.weekly-bookkeeping.complete-target', [$plan, $pickup]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $record->refresh();
        /* Record was already in progress, so its original start time must be
           preserved rather than reset by the auto-advance. */
        $this->assertTrue($record->isInProgress());
        $this->assertTrue($firstStartedAt->equalTo($record->started_at));
    }

    public function test_the_remarks_modal_never_posts_a_placeholder_target_id(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client('Client A');
        $week = $this->weekStart();

        $this->createPlan($staff, $week, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->findPlan($staff, $week);

        $html = $this->actingAs($staff)
            ->get(route('admin.weekly-bookkeeping.show', $plan))
            ->assertOk()
            ->getContent();

        /* The opener must carry the real id, and the guard script must exist so
           the placeholder action can never be submitted verbatim. */
        $target = $plan->targets()->first();
        $this->assertStringContainsString('data-bk-remarks-for="'.$target->id.'"', $html);
        $this->assertStringContainsString("indexOf('__BK_TARGET_ID__')", $html);
    }
}
