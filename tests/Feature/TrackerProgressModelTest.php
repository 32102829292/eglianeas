<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\OtherService;
use App\Models\TrackerAssignment;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerProgressModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): OtherService
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        return OtherService::query()->create([
            'client_id' => $client->id,
            'custom_label' => 'Special Filing',
            'amount' => 1500,
            'requested_at' => now(),
            'status' => OtherService::STATUS_UNPAID,
        ]);
    }

    private function makeInstance(?OtherService $service = null): TrackerInstance
    {
        $service ??= $this->makeService();

        $trackerService = TrackerService::query()->create(['name' => 'Special Filing']);

        return TrackerInstance::query()->create([
            'service_id' => $trackerService->id,
            'client_id' => $service->client_id,
            'other_service_id' => $service->id,
            'status' => TrackerInstance::STATUS_TODO,
            'date_identified' => now()->toDateString(),
        ]);
    }

    public function test_instance_links_to_billing_service_and_persists_progress_fields(): void
    {
        $service = $this->makeService();
        $instance = $this->makeInstance($service);

        $instance->fresh()->update([
            'on_hold_reason' => 'Awaiting client',
            'date_completed' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('tracker_instances', [
            'id' => $instance->id,
            'other_service_id' => $service->id,
            'on_hold_reason' => 'Awaiting client',
        ]);

        $fresh = $instance->fresh();
        $this->assertSame($service->id, $fresh->otherService->id);
        $this->assertSame($fresh->id, $service->trackerInstance->id);
        $this->assertNotNull($fresh->date_completed);
    }

    public function test_on_hold_status_is_registered(): void
    {
        $this->assertSame('On Hold', TrackerInstance::STATUSES[TrackerInstance::STATUS_ON_HOLD]);
    }

    public function test_start_marks_in_progress_and_records_start_date(): void
    {
        $instance = $this->makeInstance();

        $instance->startProcessing();

        $fresh = $instance->fresh();
        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertSame(now()->toDateString(), $fresh->date_started?->toDateString());
    }

    public function test_start_keeps_an_existing_start_date(): void
    {
        $instance = $this->makeInstance();
        $instance->update(['date_started' => '2026-08-01']);

        $instance->startProcessing();

        $this->assertSame('2026-08-01', $instance->fresh()->date_started?->toDateString());
    }

    public function test_start_rejects_non_todo_status(): void
    {
        $instance = $this->makeInstance();
        $instance->update(['status' => TrackerInstance::STATUS_DONE]);

        $this->expectException(\InvalidArgumentException::class);

        $instance->startProcessing();
    }

    public function test_hold_requires_a_reason(): void
    {
        $instance = $this->makeInstance();

        $this->expectException(\InvalidArgumentException::class);

        $instance->hold('   ');
    }

    public function test_hold_sets_on_hold_with_reason(): void
    {
        $instance = $this->makeInstance();
        $instance->startProcessing();

        $instance->hold('Awaiting BIR confirmation');

        $fresh = $instance->fresh();
        $this->assertTrue($fresh->isOnHold());
        $this->assertSame('Awaiting BIR confirmation', $fresh->on_hold_reason);
    }

    public function test_hold_rejects_non_in_progress_status(): void
    {
        $instance = $this->makeInstance();

        $this->expectException(\InvalidArgumentException::class);

        $instance->hold('A reason');
    }

    public function test_resume_returns_to_in_progress_and_clears_reason(): void
    {
        $instance = $this->makeInstance();
        $instance->startProcessing();
        $instance->hold('A reason');

        $instance->resume();

        $fresh = $instance->fresh();
        $this->assertSame(TrackerInstance::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->on_hold_reason);
    }

    public function test_complete_marks_done_and_records_completion_date(): void
    {
        $instance = $this->makeInstance();
        $instance->startProcessing();

        $instance->complete();

        $fresh = $instance->fresh();
        $this->assertTrue($fresh->isDone());
        $this->assertSame(now()->toDateString(), $fresh->date_completed?->toDateString());
    }

    public function test_complete_rejects_statuses_other_than_in_progress(): void
    {
        $instance = $this->makeInstance();

        $this->expectException(\InvalidArgumentException::class);

        $instance->complete();
    }

    public function test_double_complete_is_rejected(): void
    {
        $instance = $this->makeInstance();
        $instance->startProcessing();
        $instance->complete();

        $this->expectException(\InvalidArgumentException::class);

        $instance->complete();
    }

    public function test_activity_log_records_instance_link(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $instance = $this->makeInstance();

        ActivityLog::record($admin, 'service.started', 'Service started.', $instance);

        $log = ActivityLog::query()->first();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($instance->id, $log->tracker_instance_id);
        $this->assertSame($instance->id, $log->instance->id);
    }

    public function test_activity_log_without_instance_keeps_link_null(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        ActivityLog::record($admin, 'some.action', 'No instance.');

        $this->assertNull(ActivityLog::query()->first()->fresh()->tracker_instance_id);
    }

    public function test_activity_log_link_is_nulled_when_instance_is_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $instance = $this->makeInstance();

        ActivityLog::record($admin, 'service.started', 'Service started.', $instance);

        $instance->delete();

        $this->assertNull(ActivityLog::query()->first()->fresh()->tracker_instance_id);
    }

    public function test_is_assigned_to_matches_staff_account(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Ana Reyes']);
        $instance = $this->makeInstance();

        TrackerAssignment::query()->create([
            'instance_id' => $instance->id,
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
        ]);

        $this->assertTrue($instance->isAssignedTo($staff));
    }

    public function test_is_assigned_to_matches_staff_name_case_insensitively(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Ana Reyes']);
        $instance = $this->makeInstance();

        TrackerAssignment::query()->create([
            'instance_id' => $instance->id,
            'staff_name' => 'ANA REYES',
        ]);

        $this->assertTrue($instance->isAssignedTo($staff));
    }

    public function test_is_assigned_to_false_for_unrelated_staff(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $other = User::factory()->create(['role' => User::ROLE_STAFF]);
        $instance = $this->makeInstance();

        TrackerAssignment::query()->create([
            'instance_id' => $instance->id,
            'staff_id' => $other->id,
            'staff_name' => $other->name,
        ]);

        $this->assertFalse($instance->isAssignedTo($staff));
    }
}