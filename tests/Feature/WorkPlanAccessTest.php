<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\User;
use App\Models\WorkPlanSetting;
use App\Models\WorkPlanTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkPlanAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WorkPlanSetting::resetCache();
        WorkPlanSetting::populateCache();

        // Freeze inside the automatic planning window (Friday).
        Carbon::setTestNow('2026-11-13 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $role, string $name = null): User
    {
        return User::create([
            'name' => $name ?? ucfirst($role).' Work Plan Tester',
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    public function test_work_plan_routes_are_admin_only_and_task_routes_bind_correctly(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff1 = $this->user(User::ROLE_STAFF, 'Staff One');
        $staff2 = $this->user(User::ROLE_STAFF, 'Staff Two');
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $client = $this->user(User::ROLE_CLIENT);

        $workDate = '2026-11-10';

        $task = WorkPlanTask::create([
            'user_id' => $staff1->id,
            'work_date' => $workDate,
            'custom_title' => 'Prepare client report',
            'status' => WorkPlanTask::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);

        // 1. Client user is forbidden from the grid.
        $this->actingAs($client)
            ->get(route('admin.daily-work-plan.index'))
            ->assertForbidden();

        // 2. Staff sees only their own row in the grid.
        $this->actingAs($staff1)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee($staff1->name)
            ->assertDontSee($staff2->name);

        // 3. Staff cannot edit another staff member's cell.
        $this->actingAs($staff2)
            ->put(route('admin.daily-work-plan.update', $task), [
                'custom_title' => 'Unauthorized update',
            ])
            ->assertForbidden();

        // 4. Staff blocked after the automatic Monday 12:00 lock.
        Carbon::setTestNow('2026-11-16 12:00:00');
        $this->actingAs($staff1)
            ->post(route('admin.daily-work-plan.store'), [
                'user_id' => $staff1->id,
                'work_date' => '2026-11-20',
                'custom_title' => 'After lock',
            ])
            ->assertForbidden();

        // 5. Supervisor can edit any staff row.
        $this->actingAs($supervisor)
            ->put(route('admin.daily-work-plan.update', $task), [
                'custom_title' => 'Supervisor update',
            ])
            ->assertSessionHas('status', 'Work plan task updated successfully.');

        // 6. Admin can do everything, and an edit outside the window writes an ActivityLog entry.
        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.store'), [
                'user_id' => $staff1->id,
                'work_date' => '2026-11-20',
                'custom_title' => 'Admin override',
            ])
            ->assertSessionHas('status', 'Work plan task created successfully.');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'work_plan.override_edit',
        ]);

        // -- Feature: grid renders correctly for each role --

        // Admin sees all employees and all tasks
        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee('Staff One')
            ->assertSee('Staff Two');

        // Supervisor sees all employees and all tasks
        $this->actingAs($supervisor)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee('Staff One')
            ->assertSee('Staff Two');

        // Staff1 sees only their own row
        $this->actingAs($staff1)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee($staff1->name)
            ->assertDontSee($staff2->name);

        // Staff2 sees only their own row
        $this->actingAs($staff2)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee($staff2->name)
            ->assertDontSee($staff1->name);

        // -- Feature: accomplishment and reference-board pages --

        $this->actingAs($admin)
            ->get(route('work-plan.accomplishment', ['week_start' => '2026-11-11']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('work-plan.reference-board'))
            ->assertOk();

        // -- Query count: page should issue no more than 8 work_plan_settings queries --
        // Capture query log after request
        $queryLog = \Illuminate\Support\Facades\DB::getQueryLog();
        $settingsQueries = array_reduce($queryLog, function ($carry, $query) {
            return $carry + (str_contains($query['query'], 'work_plan_settings') ? 1 : 0);
        }, 0);
        $this->assertLessThanOrEqual(8, $settingsQueries, 'Page should issue no more than 8 work_plan_settings queries');

        // -- Existing task-route binding assertions (admin only) --

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.edit', $task))
            ->assertOk()
            ->assertJsonPath('id', $task->id);

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.tasks-for-date', [
                'user_id' => $staff1->id,
                'date' => $workDate,
            ]))
            ->assertOk()
            ->assertJsonPath('tasks.0.id', $task->id);
    }

    public function test_admin_can_create_a_work_plan_task_and_audit_entry(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $workDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString();

        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.store'), [
                'user_id' => $staff->id,
                'work_date' => $workDate,
                'custom_title' => 'Prepare client report',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Work plan task created successfully.');

        $task = WorkPlanTask::query()->where('custom_title', 'Prepare client report')->firstOrFail();

        $this->assertDatabaseHas('work_plan_audits', [
            'work_plan_task_id' => $task->id,
            'user_id' => $admin->id,
            'action' => 'created',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'work_plan_task.created',
        ]);
    }

    public function test_admin_can_update_a_work_plan_task_with_bound_fields(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $workDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString();
        $task = WorkPlanTask::create([
            'user_id' => $staff->id,
            'work_date' => $workDate,
            'custom_title' => 'Prepare client report',
            'status' => WorkPlanTask::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.daily-work-plan.update', $task), [
                'legend_code' => '',
                'custom_title' => 'Review client report',
                'description' => 'Check the completed report.',
                'status' => WorkPlanTask::STATUS_COMPLETED,
                'change_reason' => 'Reviewed by admin',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Work plan task updated successfully.');

        $this->assertDatabaseHas('work_plan_tasks', [
            'id' => $task->id,
            'custom_title' => 'Review client report',
            'description' => 'Check the completed report.',
            'status' => WorkPlanTask::STATUS_COMPLETED,
            'edited_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('work_plan_audits', [
            'work_plan_task_id' => $task->id,
            'user_id' => $admin->id,
            'action' => 'updated',
        ]);
    }
}