<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\User;
use App\Models\WorkPlanSetting;
use App\Models\WorkPlanTask;
use App\Models\WorkPlanWeeklySubmission;
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

    public function test_work_plan_access_enforces_admin_oversight_and_staff_supervisor_self_service(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin User');
        $staff1 = $this->user(User::ROLE_STAFF, 'Staff One');
        $staff2 = $this->user(User::ROLE_STAFF, 'Staff Two');
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Supervisor One');
        $client = $this->user(User::ROLE_CLIENT);

        $workDate = '2026-11-10';

        $task = WorkPlanTask::create([
            'user_id' => $staff1->id,
            'work_date' => $workDate,
            'custom_title' => 'Prepare client report',
            'status' => WorkPlanTask::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);

        // Client forbidden.
        $this->actingAs($client)->get(route('admin.daily-work-plan.index'))->assertForbidden();

        // Staff sees only own row.
        $this->actingAs($staff1)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee('Staff One')
            ->assertDontSee('Staff Two')
            ->assertDontSee('Supervisor One');

        // Supervisor sees only own row (self-only).
        $this->actingAs($supervisor)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee('Supervisor One')
            ->assertDontSee('Staff One')
            ->assertDontSee('Staff Two');

        // Admin sees all employees.
        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.index'))
            ->assertOk()
            ->assertSee('Staff One')
            ->assertSee('Staff Two')
            ->assertSee('Supervisor One');

        // Staff cannot edit another staff member's task (direct/AJAX/update).
        $this->actingAs($staff2)->put(route('admin.daily-work-plan.update', $task), ['custom_title' => 'Unauthorized update'])->assertForbidden();
        $this->actingAs($staff2)->get(route('admin.daily-work-plan.edit', $task))->assertForbidden();
        $this->actingAs($staff2)->delete(route('admin.daily-work-plan.destroy', $task))->assertForbidden();
        $this->actingAs($staff2)->get(route('admin.daily-work-plan.tasks-for-date', ['user_id' => $staff1->id, 'date' => $workDate]))->assertForbidden();

        // Supervisor cannot edit/view another employee's task (self-only).
        $this->actingAs($supervisor)->put(route('admin.daily-work-plan.update', $task), ['custom_title' => 'Supervisor unauthorized'])->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.daily-work-plan.edit', $task))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.daily-work-plan.tasks-for-date', ['user_id' => $staff1->id, 'date' => $workDate]))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.daily-work-plan.incomplete', ['week_start' => '2026-11-09']))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.daily-work-plan.performance', ['week_start' => '2026-11-09']))->assertForbidden();

        // Staff can edit own plan while window open.
        $ownTask = WorkPlanTask::create([
            'user_id' => $staff1->id,
            'work_date' => '2026-11-11',
            'custom_title' => 'Own task',
            'status' => WorkPlanTask::STATUS_PENDING,
            'created_by' => $staff1->id,
        ]);
        $this->actingAs($staff1)->put(route('admin.daily-work-plan.update', $ownTask), ['custom_title' => 'Own task updated'])->assertSessionHas('status');

        // Supervisor can edit own plan while window open.
        $supTask = WorkPlanTask::create([
            'user_id' => $supervisor->id,
            'work_date' => '2026-11-11',
            'custom_title' => 'Supervisor own',
            'status' => WorkPlanTask::STATUS_PENDING,
            'created_by' => $supervisor->id,
        ]);
        $this->actingAs($supervisor)->put(route('admin.daily-work-plan.update', $supTask), ['custom_title' => 'Supervisor own updated'])->assertSessionHas('status');

        // Lock boundary: Monday 12:00 blocks staff/supervisor.
        Carbon::setTestNow('2026-11-16 12:00:00');
        $this->actingAs($staff1)->post(route('admin.daily-work-plan.store'), ['user_id' => $staff1->id, 'work_date' => '2026-11-20', 'custom_title' => 'After lock'])->assertForbidden();
        $this->actingAs($supervisor)->post(route('admin.daily-work-plan.store'), ['user_id' => $supervisor->id, 'work_date' => '2026-11-20', 'custom_title' => 'After lock'])->assertForbidden();

        // Admin can override outside window and override is logged.
        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.store'), ['user_id' => $staff1->id, 'work_date' => '2026-11-20', 'custom_title' => 'Admin override'])
            ->assertSessionHas('status', 'Work plan task created successfully.');
        $this->assertDatabaseHas('activity_logs', ['action' => 'work_plan.override_edit']);

        // Admin-only monitoring/lock endpoints.
        $this->actingAs($staff1)->get(route('admin.daily-work-plan.performance', ['week_start' => '2026-11-09']))->assertForbidden();
        $this->actingAs($staff1)->get(route('admin.daily-work-plan.incomplete', ['week_start' => '2026-11-09']))->assertForbidden();
        $this->actingAs($staff1)->post(route('admin.daily-work-plan.toggle-lock'), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.daily-work-plan.toggle-lock'), ['reason' => 'Emergency lock'])->assertSessionHas('status');
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
        $this->assertFalse($task->isEmployeeCreated());
        $this->assertSame('Admin-assigned', $task->assignmentSourceLabel());
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

    public function test_employee_created_task_origin_and_submission_states(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Staff One');
        $other = $this->user(User::ROLE_STAFF, 'Staff Two');
        $weekStart = '2026-11-09';

        $this->actingAs($staff)->get(route('admin.daily-work-plan.index', ['week_start' => $weekStart]))->assertOk()->assertSee('Not Started');

        $this->actingAs($staff)->post(route('admin.daily-work-plan.store'), [
            'user_id' => $staff->id,
            'work_date' => '2026-11-10',
            'custom_title' => 'My own task',
        ])->assertSessionHas('status');

        $task = WorkPlanTask::query()->where('custom_title', 'My own task')->firstOrFail();
        $this->assertTrue($task->isEmployeeCreated());
        $this->assertSame('Employee-created', $task->assignmentSourceLabel());

        $draft = WorkPlanWeeklySubmission::query()->where('user_id', $staff->id)->whereDate('week_start', $weekStart)->first();
        $this->assertNotNull($draft);
        $this->assertSame(WorkPlanWeeklySubmission::STATUS_DRAFT, $draft->status);

        // cannot submit empty/other week incorrectly and cannot submit others' week context
        $this->actingAs($staff)->post(route('admin.daily-work-plan.submit-week'), ['week_start' => '2026-11-16'])->assertSessionHasErrors('week_start');

        $this->actingAs($staff)->post(route('admin.daily-work-plan.submit-week'), ['week_start' => $weekStart])->assertSessionHas('status');
        $submitted = WorkPlanWeeklySubmission::query()->where('user_id', $staff->id)->whereDate('week_start', $weekStart)->first();
        $this->assertNotNull($submitted);
        $this->assertSame(WorkPlanWeeklySubmission::STATUS_SUBMITTED, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);

        Carbon::setTestNow('2026-11-16 12:00:00');
        $this->actingAs($staff)->get(route('admin.daily-work-plan.index', ['week_start' => $weekStart]))->assertOk()->assertSee('Submitted');
    }
}
