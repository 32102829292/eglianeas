<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceHistoryTest extends TestCase
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

    private function makeInstance(string $serviceName = 'Tax Filing', string $status = 'todo', ?User $assignee = null): TrackerInstance
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

    public function test_admin_can_view_instance_history_with_timeline(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance('Tax Filing');

        ActivityLog::record($admin, 'service.created', 'Logged "Tax Filing" for Reyes Trading.', $instance);
        ActivityLog::record($admin, 'service.started', 'Started "Tax Filing" for Reyes Trading.', $instance);
        ActivityLog::record($admin, 'service.completed', 'Completed "Tax Filing" for Reyes Trading.', $instance);

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.show', $instance))
            ->assertOk()
            ->assertSee('Service history')
            ->assertSee('Tax Filing')
            ->assertSee('Reyes Trading')
            ->assertSee('service.started')
            ->assertSee('Started "Tax Filing" for Reyes Trading.')
            ->assertSee('service.completed');
    }

    public function test_history_entries_appear_in_chronological_order(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        ActivityLog::record($admin, 'service.created', 'Created entry.', $instance);
        ActivityLog::query()->latest('id')->first()->update(['created_at' => now()->subDays(2)]);

        ActivityLog::record($admin, 'service.on_hold', 'Put on hold.', $instance);
        ActivityLog::query()->latest('id')->first()->update(['created_at' => now()->subDay()]);

        ActivityLog::record($admin, 'service.resumed', 'Resumed work.', $instance);

        $content = $this->actingAs($admin)
            ->get(route('admin.service-tracker.show', $instance))
            ->assertOk()
            ->getContent();

        $created = strpos($content, 'service.created');
        $onHold = strpos($content, 'service.on_hold');
        $resumed = strpos($content, 'service.resumed');

        $this->assertNotFalse($created);
        $this->assertNotFalse($onHold);
        $this->assertNotFalse($resumed);
        $this->assertLessThan($onHold, $created);
        $this->assertLessThan($resumed, $onHold);
    }

    public function test_assigned_staff_can_view_history(): void
    {
        $staff = $this->staff('Ana Reyes');
        $instance = $this->makeInstance(assignee: $staff);

        ActivityLog::record($staff, 'service.started', 'Started by own staff.', $instance);

        $this->actingAs($staff)
            ->get(route('admin.service-tracker.show', $instance))
            ->assertOk()
            ->assertSee('service.started')
            ->assertSee('Started by own staff.');
    }

    public function test_unassigned_staff_is_forbidden(): void
    {
        $staff = $this->staff();
        $other = $this->staff('Other Person');
        $instance = $this->makeInstance(assignee: $other);

        $this->actingAs($staff)
            ->get(route('admin.service-tracker.show', $instance))
            ->assertForbidden();
    }

    public function test_client_is_forbidden(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $instance = $this->makeInstance();

        $this->actingAs($client)
            ->get(route('admin.service-tracker.show', $instance))
            ->assertForbidden();
    }

    public function test_show_page_works_for_instance_without_any_history(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.show', $instance))
            ->assertOk()
            ->assertSee('No history recorded yet for this service.');
    }

    public function test_index_lists_history_links(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.index'))
            ->assertOk()
            ->assertSee('/service-tracker/'.$instance->id.'/history', false);
    }

    public function test_timeline_shows_readable_event_labels(): void
    {
        $admin = $this->admin();
        $instance = $this->makeInstance();

        ActivityLog::record($admin, 'service.started', 'Started work.', $instance);

        $this->actingAs($admin)
            ->get(route('admin.service-tracker.show', $instance))
            ->assertOk()
            ->assertSee('Work started')
            ->assertSee('service.started');
    }

    public function test_assignment_toggle_log_is_linked_to_instance(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('Ana Reyes');
        $instance = $this->makeInstance(assignee: $staff);
        $assignment = $instance->assignments->first();

        $this->actingAs($admin)
            ->post(route('admin.service-tracker.toggle-assignment', $assignment))
            ->assertRedirect();

        $log = ActivityLog::query()->where('action', 'admin.tracker_assignment_toggled')->first();
        $this->assertSame($instance->id, $log->tracker_instance_id);
        $this->assertStringContainsString('as done on', (string) $log->description);
    }
}