<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\KaizenConcern;
use App\Models\PriorityItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? ucfirst($role).' Tester',
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function concern(string $type = KaizenConcern::TYPE_EMPLOYEE_SUGGESTION, ?User $assignee = null): KaizenConcern
    {
        return KaizenConcern::create([
            'type' => $type,
            'date_identified' => now()->subDay(),
            'challenge' => 'Invoices are filed late',
            'recommended_solution' => 'Add a weekly filing checklist.',
            'assigned_staff_id' => $assignee?->id,
            'status' => KaizenConcern::STATUS_PENDING,
            'created_by' => $this->user(User::ROLE_ADMIN)->id,
        ]);
    }

    private function item(?User $assignee = null): PriorityItem
    {
        return PriorityItem::create([
            'task_lesson' => 'Reconcile the cash book',
            'type' => PriorityItem::TYPE_PRIORITY_TASK,
            'description' => 'Reconcile the cash book before month end.',
            'priority' => PriorityItem::PRIORITY_MEDIUM,
            'assigned_staff_id' => $assignee?->id,
            'due_date' => now()->addDays(3),
            'status' => PriorityItem::STATUS_PENDING,
            'created_by' => $this->user(User::ROLE_ADMIN)->id,
        ]);
    }

    public function test_admin_can_assign_staff_to_a_priority_item_from_the_list(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF, 'Angeli');
        $item = $this->item();

        $this->actingAs($admin)
            ->from(route('admin.priority-items.index'))
            ->post(route('admin.priority-items.assign-staff', $item), [
                'assigned_staff_id' => $staff->id,
            ])
            ->assertRedirect(route('admin.priority-items.index'))
            ->assertSessionHas('status');

        $this->assertSame($staff->id, (int) $item->fresh()->assigned_staff_id);

        $this->actingAs($admin)
            ->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('Angeli');
    }

    public function test_supervisor_can_assign_an_unassigned_priority_item(): void
    {
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);
        $item = $this->item();

        $this->actingAs($supervisor)
            ->from(route('admin.priority-items.show', $item))
            ->post(route('admin.priority-items.assign-staff', $item), [
                'assigned_staff_id' => $staff->id,
            ])
            ->assertRedirect(route('admin.priority-items.index'));

        $this->assertSame($staff->id, (int) $item->fresh()->assigned_staff_id);
    }

    public function test_admin_and_supervisor_can_assign_employee_suggestions(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $adminConcern = $this->concern();
        $this->actingAs($admin)
            ->from(route('admin.kaizen-concerns.show', $adminConcern))
            ->post(route('admin.kaizen-concerns.assign-staff', $adminConcern), [
                'assigned_staff_id' => $staff->id,
            ])
            ->assertRedirect(route('admin.kaizen-concerns.show', $adminConcern));

        $this->assertSame($staff->id, (int) $adminConcern->fresh()->assigned_staff_id);

        $supervisorConcern = $this->concern();
        $this->actingAs($supervisor)
            ->from(route('admin.kaizen-concerns.index'))
            ->post(route('admin.kaizen-concerns.assign-staff', $supervisorConcern), [
                'assigned_staff_id' => $staff->id,
            ])
            ->assertRedirect(route('admin.kaizen-concerns.index'));

        $this->assertSame($staff->id, (int) $supervisorConcern->fresh()->assigned_staff_id);
    }

    public function test_supervisor_can_reassign_an_admin_concern_assigned_to_them(): void
    {
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->concern(KaizenConcern::TYPE_ADMIN_CONCERN, $supervisor);

        $this->actingAs($supervisor)
            ->from(route('admin.kaizen-concerns.show', $concern))
            ->post(route('admin.kaizen-concerns.assign-staff', $concern), [
                'assigned_staff_id' => $staff->id,
            ])
            ->assertRedirect(route('admin.kaizen-concerns.admin-concerns.index'));

        $this->assertSame($staff->id, (int) $concern->fresh()->assigned_staff_id);
    }

    public function test_supervisor_cannot_assign_an_admin_concern_they_cannot_see(): void
    {
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $otherSupervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $unassigned = $this->concern(KaizenConcern::TYPE_ADMIN_CONCERN);

        $this->actingAs($supervisor)
            ->post(route('admin.kaizen-concerns.assign-staff', $unassigned), [
                'assigned_staff_id' => $staff->id,
            ])
            ->assertForbidden();

        $someoneElses = $this->concern(KaizenConcern::TYPE_ADMIN_CONCERN, $otherSupervisor);

        $this->actingAs($supervisor)
            ->post(route('admin.kaizen-concerns.assign-staff', $someoneElses), [
                'assigned_staff_id' => $staff->id,
            ])
            ->assertForbidden();

        $this->assertNull($unassigned->fresh()->assigned_staff_id);
        $this->assertSame($otherSupervisor->id, (int) $someoneElses->fresh()->assigned_staff_id);
    }

    public function test_admin_can_assign_an_admin_concern_to_a_supervisor(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Sofia Supervisor');
        $concern = $this->concern(KaizenConcern::TYPE_ADMIN_CONCERN);

        $this->actingAs($admin)
            ->from(route('admin.kaizen-concerns.admin-concerns.index'))
            ->post(route('admin.kaizen-concerns.assign-staff', $concern), [
                'assigned_staff_id' => $supervisor->id,
            ])
            ->assertRedirect(route('admin.kaizen-concerns.admin-concerns.index'));

        $this->assertSame($supervisor->id, (int) $concern->fresh()->assigned_staff_id);

        $this->actingAs($admin)
            ->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->assertSee('Sofia Supervisor');
    }

    public function test_staff_cannot_assign_either_module(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $assignee = $this->user(User::ROLE_STAFF);
        $item = $this->item();
        $concern = $this->concern();

        $this->actingAs($staff)
            ->post(route('admin.priority-items.assign-staff', $item), [
                'assigned_staff_id' => $assignee->id,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.kaizen-concerns.assign-staff', $concern), [
                'assigned_staff_id' => $assignee->id,
            ])
            ->assertForbidden();

        $this->assertNull($item->fresh()->assigned_staff_id);
        $this->assertNull($concern->fresh()->assigned_staff_id);
    }

    public function test_only_active_staff_accounts_can_be_assigned(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $client = $this->user(User::ROLE_CLIENT);
        $deletedStaff = $this->user(User::ROLE_STAFF);
        $deletedStaff->delete();
        $item = $this->item();

        $this->actingAs($admin)
            ->from(route('admin.priority-items.index'))
            ->post(route('admin.priority-items.assign-staff', $item), [
                'assigned_staff_id' => $client->id,
            ])
            ->assertSessionHasErrors('assigned_staff_id');

        $this->actingAs($admin)
            ->from(route('admin.priority-items.index'))
            ->post(route('admin.priority-items.assign-staff', $item), [
                'assigned_staff_id' => $deletedStaff->id,
            ])
            ->assertSessionHasErrors('assigned_staff_id');

        $this->assertNull($item->fresh()->assigned_staff_id);
    }

    public function test_admin_can_assign_a_priority_item_to_a_supervisor(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Sofia Supervisor');
        $item = $this->item();

        $this->actingAs($admin)
            ->from(route('admin.priority-items.index'))
            ->post(route('admin.priority-items.assign-staff', $item), [
                'assigned_staff_id' => $supervisor->id,
            ])
            ->assertRedirect(route('admin.priority-items.index'));

        $this->assertSame($supervisor->id, (int) $item->fresh()->assigned_staff_id);

        $this->actingAs($admin)
            ->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('Sofia Supervisor');
    }

    public function test_assignment_controls_are_visible_to_managers_but_not_staff(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF, 'Angeli');
        $item = $this->item();
        $concern = $this->concern();

        $this->actingAs($admin)
            ->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee(route('admin.priority-items.assign-staff', $item), false)
            ->assertSee('Angeli');

        $this->actingAs($admin)
            ->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->assertSee(route('admin.kaizen-concerns.assign-staff', $concern), false);

        $this->actingAs($staff)
            ->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertDontSee(route('admin.priority-items.assign-staff', $item), false);
    }
}
