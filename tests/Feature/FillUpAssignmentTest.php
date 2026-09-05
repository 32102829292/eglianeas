<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\OtherService;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillUpAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const STORE_ROUTE = 'admin.other-services.store';

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'business_name' => 'Reyes Trading',
        ]);
    }

    public function test_fill_up_form_renders_with_staff_roster(): void
    {
        User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Ana Reyes']);

        $this->actingAs($this->admin())
            ->get(route('admin.other-services.fill-up'))
            ->assertOk()
            ->assertSee('Ana Reyes')
            ->assertSee('staff_ids');
    }

    public function test_fill_up_creates_service_and_linked_tracker_with_assignments(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $staffA = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Ana Reyes']);
        $staffB = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Bob Cruz']);

        $this->actingAs($admin)
            ->post(route(self::STORE_ROUTE), [
                'client_id' => $client->id,
                'custom_label' => 'Special Filing',
                'amount' => 1500,
                'requested_at' => '2026-08-03',
                'notes' => 'Please prioritize.',
                'staff_ids' => [$staffA->id, $staffB->id],
            ])
            ->assertRedirect(route('admin.other-services.billing'))
            ->assertSessionHas('status', 'Service request created.');

        $this->assertDatabaseHas('other_services', [
            'client_id' => $client->id,
            'custom_label' => 'Special Filing',
            'status' => OtherService::STATUS_UNPAID,
        ]);

        $service = OtherService::query()->where('custom_label', 'Special Filing')->firstOrFail();

        $this->assertDatabaseHas('tracker_instances', [
            'client_id' => $client->id,
            'other_service_id' => $service->id,
            'status' => TrackerInstance::STATUS_TODO,
        ]);

        $instance = $service->trackerInstance;
        $this->assertNotNull($instance);
        $this->assertSame('2026-08-03', $instance->date_identified?->toDateString());
        $this->assertSame(
            TrackerService::query()->where('name', 'Special Filing')->firstOrFail()->id,
            $instance->service_id
        );

        $this->assertSame(
            [$staffA->id, $staffB->id],
            $instance->assignments->sortBy('id')->pluck('staff_id')->all()
        );
        $this->assertTrue($instance->assignments->contains('staff_name', 'Bob Cruz'));
        $this->assertFalse($instance->assignments->contains('completed', true));

        $events = ActivityLog::query()
            ->where('tracker_instance_id', $instance->id)
            ->pluck('action')
            ->all();
        $this->assertContains('service.created', $events);
        $this->assertContains('service.staff_assigned', $events);
    }

    public function test_fill_up_without_staff_still_creates_tracker_instance(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route(self::STORE_ROUTE), [
                'client_id' => $client->id,
                'custom_label' => 'No Staff Filing',
                'amount' => 0,
                'requested_at' => '2026-08-03',
            ])
            ->assertRedirect(route('admin.other-services.billing'));

        $service = OtherService::query()->where('custom_label', 'No Staff Filing')->firstOrFail();

        $instance = $service->trackerInstance;
        $this->assertNotNull($instance);
        $this->assertSame(0, $instance->assignments()->count());
        $this->assertDatabaseCount('tracker_assignments', 0);
    }

    public function test_fill_up_rejects_non_staff_user_ids(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route(self::STORE_ROUTE), [
                'client_id' => $client->id,
                'custom_label' => 'Bad Assignee',
                'amount' => 100,
                'staff_ids' => [$client->id],
            ])
            ->assertSessionHasErrors('staff_ids.0');

        $this->assertDatabaseCount('other_services', 0);
        $this->assertDatabaseCount('tracker_instances', 0);
    }

    public function test_fill_up_rejects_duplicate_staff_ids(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($admin)
            ->post(route(self::STORE_ROUTE), [
                'client_id' => $client->id,
                'custom_label' => 'Dup Staff',
                'amount' => 100,
                'staff_ids' => [$staff->id, $staff->id],
            ])
            ->assertSessionHasErrors('staff_ids.1');

        $this->assertDatabaseCount('other_services', 0);
    }

    public function test_fill_up_rejects_unknown_staff_ids(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route(self::STORE_ROUTE), [
                'client_id' => $client->id,
                'custom_label' => 'Missing Staff',
                'amount' => 100,
                'staff_ids' => [999999],
            ])
            ->assertSessionHasErrors('staff_ids.0');

        $this->assertDatabaseCount('other_services', 0);
    }

    public function test_fill_up_reuses_matching_tracker_service_by_label(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $trackerService = TrackerService::query()->create(['name' => 'Special Filing']);

        $this->actingAs($admin)
            ->post(route(self::STORE_ROUTE), [
                'client_id' => $client->id,
                'custom_label' => 'special filing',
                'amount' => 100,
            ])
            ->assertRedirect(route('admin.other-services.billing'));

        $service = OtherService::query()->where('custom_label', 'special filing')->firstOrFail();

        $this->assertSame($trackerService->id, $service->trackerInstance->service_id);
        $this->assertSame(1, TrackerService::query()->count());
    }

    public function test_fill_up_creates_tracker_service_when_no_match(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route(self::STORE_ROUTE), [
                'client_id' => $client->id,
                'custom_label' => 'Brand New Filing',
                'amount' => 100,
            ])
            ->assertRedirect(route('admin.other-services.billing'));

        $service = OtherService::query()->where('custom_label', 'Brand New Filing')->firstOrFail();
        $trackerService = TrackerService::query()->where('name', 'Brand New Filing')->firstOrFail();

        $this->assertNotNull($service->trackerInstance);
        $this->assertSame($trackerService->id, $service->trackerInstance->service_id);
    }
}