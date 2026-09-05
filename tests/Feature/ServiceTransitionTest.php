<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\OtherService;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTransitionTest extends TestCase
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

    private function staff(string $name = 'Staff Member'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STAFF,
            'name' => $name,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function makeInstance(string $serviceName = 'Special Filing', string $status = 'todo', ?User $assignee = null): TrackerInstance
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'business_name' => 'Reyes Trading']);
        $trackerService = TrackerService::query()->create(['name' => $serviceName]);

        $instance = TrackerInstance::query()->create([
            'service_id' => $trackerService->id,
            'client_id' => $client->id,
            'status' => $status,
            'date_identified' => now()->toDateString(),
        ]);

        if ($assignee) {
            $instance->assignments()->create([
                'staff_id' => $assignee->id,
                'staff_name' => $assignee->name,
                'completed' => false,
            ]);
        }

        return $instance;
    }

    public function test_assigned_staff_can_start_own_instance(): void
    {
        $staff = $this->staff('Ana Reyes');
        $instance = $this->makeInstance(assignee: $staff);

        $this->actingAs($staff)
            ->post(route('admin.service-tracker.start', $instance))
            ->assertRedirect()
            ->assertSessionHas('status', 'Service marked as in progress.');

        $fresh = $instance->fresh();
        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertSame(now()->toDateString(), $fresh->date_started?->toDateString());

        $log = ActivityLog::query()->where('tracker_instance_id', $instance->id)->first();
        $this->assertSame('service.started', $log->action);
        $this->assertSame($staff->id, $log->user_id);
    }

    public function test_admin_can_start_any_instance(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.start', $instance))
            ->assertRedirect()
            ->assertSessionHas('status', 'Service marked as in progress.');

        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $instance->fresh()->status);
    }

    public function test_unassigned_staff_cannot_start(): void
    {
        $staff = $this->staff();
        $other = $this->staff('Other Person');
        $instance = $this->makeInstance(assignee: $other);

        $this->actingAs($staff)
            ->post(route('admin.service-tracker.start', $instance))
            ->assertForbidden();

        $this->assertSame(TrackerInstance::STATUS_TODO, $instance->fresh()->status);
    }

    public function test_client_cannot_start(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $instance = $this->makeInstance();

        $this->actingAs($client)
            ->post(route('admin.service-tracker.start', $instance))
            ->assertForbidden();

        $this->assertSame(TrackerInstance::STATUS_TODO, $instance->fresh()->status);
    }

    public function test_start_rejects_an_in_progress_instance(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance(status: TrackerInstance::STATUS_IN_PROGRESS);

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.start', $instance))
            ->assertRedirect()
            ->assertSessionHasErrors('action');

        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $instance->fresh()->status);
    }

    public function test_hold_requires_a_reason(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();
        $instance->startProcessing();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.hold', $instance), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $instance->fresh()->status);
    }

    public function test_hold_sets_on_hold_and_logs_event(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();
        $instance->startProcessing();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.hold', $instance), ['reason' => 'Awaiting client docs'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Service put on hold.');

        $fresh = $instance->fresh();
        $this->assertTrue($fresh->isOnHold());
        $this->assertSame('Awaiting client docs', $fresh->on_hold_reason);

        $log = ActivityLog::query()->where('tracker_instance_id', $instance->id)->first();
        $this->assertSame('service.on_hold', $log->action);
        $this->assertStringContainsString('Awaiting client docs', (string) $log->description);
    }

    public function test_hold_rejects_a_todo_instance(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.hold', $instance), ['reason' => 'Testing'])
            ->assertRedirect()
            ->assertSessionHasErrors('action');

        $this->assertSame(TrackerInstance::STATUS_TODO, $instance->fresh()->status);
    }

    public function test_resume_returns_to_in_progress(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();
        $instance->startProcessing();
        $instance->hold('Waiting on client');

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.resume', $instance))
            ->assertRedirect()
            ->assertSessionHas('status', 'Service resumed.');

        $fresh = $instance->fresh();
        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->on_hold_reason);
    }

    public function test_resume_rejects_when_not_on_hold(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.resume', $instance))
            ->assertRedirect()
            ->assertSessionHasErrors('action');

        $this->assertSame(TrackerInstance::STATUS_TODO, $instance->fresh()->status);
    }

    public function test_complete_marks_done_and_records_completion_date(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();
        $instance->startProcessing();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.complete', $instance))
            ->assertRedirect()
            ->assertSessionHas('status', 'Service completed.');

        $fresh = $instance->fresh();
        $this->assertTrue($fresh->isDone());
        $this->assertSame(now()->toDateString(), $fresh->date_completed?->toDateString());

        $log = ActivityLog::query()->where('tracker_instance_id', $instance->id)->first();
        $this->assertSame('service.completed', $log->action);
    }

    public function test_complete_rejects_a_todo_instance(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.complete', $instance))
            ->assertRedirect()
            ->assertSessionHasErrors('action');

        $this->assertSame(TrackerInstance::STATUS_TODO, $instance->fresh()->status);
    }

    public function test_double_complete_is_rejected(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();
        $instance->startProcessing();
        $instance->complete();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.complete', $instance))
            ->assertRedirect()
            ->assertSessionHasErrors('action');

        $this->assertSame(TrackerInstance::STATUS_DONE, $instance->fresh()->status);
    }

    public function test_index_renders_action_buttons_per_status_for_admin(): void
    {
        $admin = $this->admin();
        $this->makeInstance('Todo Filing', TrackerInstance::STATUS_TODO);
        $this->makeInstance('In Progress Filing', TrackerInstance::STATUS_IN_PROGRESS);
        $this->makeInstance('On Hold Filing', TrackerInstance::STATUS_ON_HOLD);
        $this->makeInstance('Done Filing', TrackerInstance::STATUS_DONE);

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.index'))
            ->assertOk()
            ->assertSee('Todo Filing')
            ->assertSee('In Progress Filing')
            ->assertSee('On Hold Filing')
            ->assertSee('Done Filing')
            ->assertSee('>Start<', false)
            ->assertSee('placeholder="Hold reason', false)
            ->assertSee('>Complete<', false)
            ->assertSee('>Resume<', false);
    }

    public function test_assigned_staff_sees_only_own_instances_with_actions(): void
    {
        $staff = $this->staff('Ana Reyes');
        $this->makeInstance('Mine Filing', TrackerInstance::STATUS_TODO, $staff);
        $this->makeInstance('Yours Filing', TrackerInstance::STATUS_TODO);

        $this->actingAs($staff)
            ->get(route('admin.service-tracker.index'))
            ->assertOk()
            ->assertSee('Mine Filing')
            ->assertDontSee('fw-semibold">Yours Filing</div>', false)
            ->assertDontSee('cv-value">Yours Filing</span>', false)
            ->assertSee('>Start<', false);
    }

    public function test_done_instances_have_no_action_buttons(): void
    {
        $admin = $this->admin();
        $this->makeInstance('Done Filing', TrackerInstance::STATUS_DONE);

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.index', ['status' => 'done']))
            ->assertOk()
            ->assertSee('Done Filing')
            ->assertDontSee('>Start<', false)
            ->assertDontSee('>Complete<', false)
            ->assertDontSee('>Resume<', false);
    }

    public function test_index_shows_due_and_completed_dates(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance('Filing', TrackerInstance::STATUS_DONE);
        $instance->update(['date_completed' => now()->toDateString()]);

        $other = OtherService::query()->create([
            'client_id' => $instance->client_id,
            'custom_label' => 'Filing',
            'amount' => 1500,
            'requested_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'unpaid',
        ]);
        $instance->update(['other_service_id' => $other->id]);

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.index'))
            ->assertOk()
            ->assertSee('Due')
            ->assertSee('Completed')
            ->assertSee($instance->fresh()->date_completed->format('M j, Y'))
            ->assertSee($other->fresh()->due_date->format('M j, Y'));
    }
}