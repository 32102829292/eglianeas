<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\OtherService;
use App\Models\TrackerAssignment;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceReassignTest extends TestCase
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

    private function makeInstance(
        string $status = 'todo',
        ?User $assignee = null,
        bool $assignmentCompleted = false,
        ?string $notes = null,
    ): TrackerInstance {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'business_name' => 'Reyes Trading']);
        $trackerService = TrackerService::query()->create(['name' => 'Special Filing']);

        $timestamps = [];
        if ($status === TrackerInstance::STATUS_IN_PROGRESS) {
            $timestamps['date_started'] = now()->subDays(2)->toDateString();
        } elseif ($status === TrackerInstance::STATUS_ON_HOLD) {
            $timestamps['date_started'] = now()->subDays(2)->toDateString();
        } elseif ($status === TrackerInstance::STATUS_DONE) {
            $timestamps['date_started'] = now()->subDays(5)->toDateString();
            $timestamps['date_completed'] = now()->subDay()->toDateString();
        }

        $instance = TrackerInstance::query()->create([
            'service_id' => $trackerService->id,
            'client_id' => $client->id,
            'status' => $status,
            'date_identified' => now()->subDays(10)->toDateString(),
            'notes' => $notes,
            ...$timestamps,
        ]);

        if ($status === TrackerInstance::STATUS_ON_HOLD) {
            $instance->update(['on_hold_reason' => 'Awaiting client documents']);
        }

        if ($assignee) {
            $instance->assignments()->create([
                'staff_id' => $assignee->id,
                'staff_name' => $assignee->name,
                'completed' => $assignmentCompleted,
                'completed_at' => $assignmentCompleted ? now()->subDay() : null,
            ]);
        }

        return $instance;
    }

    private function reassign(User $admin, TrackerInstance $instance, User $newStaff)
    {
        return $this->actingAs($admin)->put(
            route('admin.service-tracker.update-assignment', $instance),
            ['staff_id' => $newStaff->id]
        );
    }

    public function test_a_admin_sees_change_staff_button_for_done_instance(): void
    {
        $admin = $this->admin();
        $this->makeInstance('done', $this->staff('Ana Reyes'));

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.index', ['status' => 'done']))
            ->assertOk()
            ->assertSee('Change Staff', false)
            ->assertSee('/admin/service-tracker/1/assignment', false)
            ->assertSee('id="reassignModal"', false);
    }

    public function test_a2_change_staff_button_is_admin_only(): void
    {
        $staff = $this->staff('Ana Reyes');
        $this->makeInstance('todo', $staff);

        $this->actingAs($staff)
            ->get(route('admin.service-tracker.index'))
            ->assertOk()
            ->assertDontSee('Change Staff', false)
            ->assertDontSee('service-tracker.update-assignment', false);
    }

    public function test_b_reassign_pending_instance_from_a_to_b(): void
    {
        $admin = $this->admin();
        $ana = $this->staff('Ana Reyes');
        $bob = $this->staff('Bob Cruz');
        $instance = $this->makeInstance('todo', $ana);

        $this->actingAs($admin)->put(
            route('admin.service-tracker.update-assignment', $instance),
            ['staff_id' => $bob->id]
        )->assertRedirect()->assertSessionHas('status', 'Assigned staff changed.');

        $fresh = $instance->fresh('assignments');
        $this->assertSame(TrackerInstance::STATUS_TODO, $fresh->status);
        $this->assertSame(1, $fresh->assignments->count());
        $this->assertSame($bob->id, $fresh->assignments->first()->staff_id);
        $this->assertSame($bob->name, $fresh->assignments->first()->staff_name);
        $this->assertFalse($fresh->assignments->first()->completed);

        $notification = Notification::query()->first();
        $this->assertSame($bob->id, $notification->user_id);
        $this->assertSame('staff_assignment', $notification->type);
        $this->assertStringContainsString('You have been assigned to Special Filing for', $notification->body);
        $this->assertSame(route('admin.service-tracker.show', $instance), $notification->link);

        $log = ActivityLog::query()->where('tracker_instance_id', $instance->id)->first();
        $this->assertSame('service.staff_assigned', $log->action);
        $this->assertStringContainsString($ana->name, (string) $log->description);
        $this->assertStringContainsString($bob->name, (string) $log->description);
    }

    public function test_c_reassign_in_progress_instance_keeps_status_and_start_date(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance('in_progress', $this->staff('Ana Reyes'));

        $this->reassign($admin, $instance, $this->staff('Bob Cruz'))->assertRedirect();

        $fresh = $instance->fresh('assignments');
        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNotNull($fresh->date_started);
    }

    public function test_d_reassign_on_hold_instance_keeps_hold_status_and_reason(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance('on_hold', $this->staff('Ana Reyes'));

        $this->reassign($admin, $instance, $this->staff('Bob Cruz'))->assertRedirect();

        $fresh = $instance->fresh('assignments');
        $this->assertTrue($fresh->isOnHold());
        $this->assertSame('Awaiting client documents', $fresh->on_hold_reason);
    }

    public function test_e_reassign_completed_instance_keeps_completion(): void
    {
        $admin = $this->admin();
        $ana = $this->staff('Ana Reyes');
        $bob = $this->staff('Bob Cruz');
        $instance = $this->makeInstance('done', $ana, assignmentCompleted: true);

        $this->reassign($admin, $instance, $bob)->assertRedirect();

        $fresh = $instance->fresh('assignments');
        $this->assertSame(TrackerInstance::STATUS_DONE, $fresh->status);
        $this->assertNotNull($fresh->date_completed);
        $this->assertSame(1, $fresh->assignments->count());
        $this->assertTrue($fresh->assignments->first()->completed);
        $this->assertNotNull($fresh->assignments->first()->completed_at);
        $this->assertSame($bob->name, $fresh->assignments->first()->staff_name);
    }

    public function test_f_assign_to_staff_when_unassigned_creates_assignment(): void
    {
        $admin = $this->admin();
        $bob = $this->staff('Bob Cruz');
        $instance = $this->makeInstance('todo');

        $this->reassign($admin, $instance, $bob)->assertRedirect();

        $fresh = $instance->fresh('assignments');
        $this->assertSame(1, $fresh->assignments->count());
        $this->assertSame($bob->id, $fresh->assignments->first()->staff_id);
        $this->assertSame(TrackerInstance::STATUS_TODO, $fresh->status);
    }

    public function test_g_newly_assigned_staff_is_notified_exactly_once(): void
    {
        $admin = $this->admin();
        $bob = $this->staff('Bob Cruz');
        $instance = $this->makeInstance('todo', $this->staff('Ana Reyes'));

        $this->reassign($admin, $instance, $bob)->assertRedirect();

        $count = Notification::query()->where('user_id', $bob->id)->where('type', 'staff_assignment')->count();
        $this->assertSame(1, $count);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_h_instance_data_progress_and_history_are_preserved(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance('in_progress', $this->staff('Ana Reyes'), notes: 'Client prefers email updates.');
        $originalStarted = $instance->date_started?->toDateString();

        $this->reassign($admin, $instance, $this->staff('Bob Cruz'))->assertRedirect();

        $fresh = $instance->fresh('assignments');
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame($originalStarted, $fresh->date_started?->toDateString());
        $this->assertSame('Client prefers email updates.', $fresh->notes);
        $this->assertSame(1, $fresh->assignments->count());
        $this->assertSame(1, ActivityLog::query()->where('tracker_instance_id', $instance->id)->where('action', 'service.staff_assigned')->count());
    }

    public function test_i_staff_and_client_cannot_change_assignment(): void
    {
        $ana = $this->staff('Ana Reyes');
        $bob = $this->staff('Bob Cruz');
        $instance = $this->makeInstance('todo', $ana);

        $this->actingAs($ana)
            ->put(route('admin.service-tracker.update-assignment', $instance), ['staff_id' => $bob->id])
            ->assertForbidden();

        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $this->actingAs($client)
            ->put(route('admin.service-tracker.update-assignment', $instance), ['staff_id' => $bob->id])
            ->assertForbidden();

        $this->assertSame($ana->id, $instance->fresh('assignments')->assignments->first()->staff_id);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_j_invalid_or_inactive_staff_is_rejected(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance('todo', $this->staff('Ana Reyes'));

        $nonexistent = 999999;
        $this->actingAs($admin)
            ->put(route('admin.service-tracker.update-assignment', $instance), ['staff_id' => $nonexistent])
            ->assertRedirect()
            ->assertSessionHasErrors('staff_id');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $this->actingAs($admin)
            ->put(route('admin.service-tracker.update-assignment', $instance), ['staff_id' => $client->id])
            ->assertRedirect()
            ->assertSessionHasErrors('staff_id');

        $former = $this->staff('Former Staff');
        $former->delete();
        $this->actingAs($admin)
            ->put(route('admin.service-tracker.update-assignment', $instance), ['staff_id' => $former->id])
            ->assertRedirect()
            ->assertSessionHasErrors('staff_id');

        $this->assertSame('Ana Reyes', $instance->fresh('assignments')->assignments->first()->staff_name);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_k_double_submit_does_not_create_duplicate_notifications(): void
    {
        $admin = $this->admin();
        $bob = $this->staff('Bob Cruz');
        $instance = $this->makeInstance('todo', $this->staff('Ana Reyes'));

        $this->actingAs($admin)
            ->put(route('admin.service-tracker.update-assignment', $instance), ['staff_id' => $bob->id])
            ->assertRedirect()
            ->assertSessionHas('status', 'Assigned staff changed.');

        $first = TrackerAssignment::query()->first();
        $this->assertSame($bob->id, $first->staff_id);

        $this->actingAs($admin)
            ->put(route('admin.service-tracker.update-assignment', $instance), ['staff_id' => $bob->id])
            ->assertRedirect()
            ->assertSessionHas('status', "{$bob->name} is already assigned to this service.");

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('tracker_assignments', 1);
    }
}