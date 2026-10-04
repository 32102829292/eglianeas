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
            ->assertSee('Selected tasks');

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
}