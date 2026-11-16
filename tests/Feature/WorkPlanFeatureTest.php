<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\User;
use App\Models\WorkPlanAudit;
use App\Models\WorkPlanLegend;
use App\Models\WorkPlanSetting;
use App\Models\WorkPlanTask;
use App\Models\WorkPlanTaskProblem;
use App\Models\WorkPlanWeeklyEvaluation;
use App\Services\WorkPlanScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkPlanFeatureTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK_START = '2026-11-09';

    private const FRIDAY = '2026-11-13 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::FRIDAY);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' Tester',
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function legend(string $code = 'A', string $name = 'BIR Registration / New COR', ?User $creator = null): WorkPlanLegend
    {
        return WorkPlanLegend::create([
            'code' => $code,
            'name' => $name,
            'category' => 'Tax & Registration',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $creator?->id ?? User::query()->value('id'),
        ]);
    }

    private function task(User $staff, User $creator, string $date = '2026-11-09', array $overrides = []): WorkPlanTask
    {
        return WorkPlanTask::create(array_merge([
            'user_id' => $staff->id,
            'work_date' => $date,
            'legend_code' => 'A',
            'status' => WorkPlanTask::STATUS_PENDING,
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_default_legends_match_the_official_workbook(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $count = WorkPlanLegend::initializeDefaultLegends($admin->id);

        $this->assertSame(28, $count);
        $this->assertDatabaseHas('work_plan_legends', ['code' => 'A', 'name' => 'BIR Registration / New COR']);
        $this->assertDatabaseHas('work_plan_legends', ['code' => 'F', 'name' => 'BOOKKEEPING']);
        $this->assertDatabaseHas('work_plan_legends', ['code' => 'S', 'name' => 'Rest Day / Leave / Holiday']);
        $this->assertDatabaseHas('work_plan_legends', ['code' => 'T1', 'name' => 'Priority / Rush Client']);
        $this->assertDatabaseHas('work_plan_legends', ['code' => 'T8', 'name' => 'Others (write details)']);
    }

    public function test_admin_can_manage_legends_but_staff_cannot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        $this->actingAs($admin)
            ->post(route('admin.work-plan-legends.store'), [
                'code' => 'T9',
                'name' => 'Special assignment',
                'category' => 'Special/Project',
                'sort_order' => 29,
                'is_active' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Activity legend created successfully.');

        $legend = WorkPlanLegend::query()->where('code', 'T9')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.work-plan-legends.update', $legend), [
                'code' => 'T9',
                'name' => 'Special assignment (revised)',
                'category' => 'Special/Project',
                'sort_order' => 29,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('work_plan_legends', [
            'id' => $legend->id,
            'name' => 'Special assignment (revised)',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.work-plan-legends.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.work-plan-legends.store'), ['code' => 'Z', 'name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_legend_snapshots_preserve_history_across_renames(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $legend = $this->legend('A', 'BIR Registration / New COR', $admin);

        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-09',
                'legend_code' => 'A',
            ])
            ->assertSessionHas('status', 'Work plan task created successfully.');

        $task = WorkPlanTask::query()->firstOrFail();
        $this->assertSame('BIR Registration / New COR', $task->legend_name_snapshot);
        $this->assertStringContainsString('BIR Registration / New COR', $task->getLegendLabel());

        $this->actingAs($admin)
            ->put(route('admin.work-plan-legends.update', $legend), [
                'code' => 'A',
                'name' => 'Renamed Activity',
                'category' => 'Tax & Registration',
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('BIR Registration / New COR', $task->legend_name_snapshot);
        $this->assertStringContainsString('BIR Registration / New COR', $task->getLegendLabel());
        $this->assertStringNotContainsString('Renamed Activity', $task->getLegendLabel());

        $this->actingAs($admin)
            ->put(route('admin.work-plan-legends.update', $legend), [
                'code' => 'Z',
                'name' => 'Renamed Activity',
                'category' => 'Tax & Registration',
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('A', $task->legend_code);
        $this->assertSame('BIR Registration / New COR', $task->legend_name_snapshot);
    }

    public function test_reference_board_is_available_to_staff(): void
    {
        $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        $this->actingAs($staff)
            ->get(route('work-plan.reference-board'))
            ->assertOk()
            ->assertSee('BIR Registration / New COR')
            ->assertSee('Activity Legend Reference Board');
    }

    public function test_automatic_planning_window_boundaries_are_configurable(): void
    {
        $this->assertTrue(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-13 00:00:00')));
        $this->assertTrue(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-14 12:00:00')));
        $this->assertTrue(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-15 23:59:00')));
        $this->assertTrue(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-16 11:59:00')));
        $this->assertFalse(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-16 12:00:00')));
        $this->assertFalse(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-17 09:00:00')));
        $this->assertFalse(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-19 23:59:00')));
        $this->assertTrue(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-20 00:00:00')));

        config([
            'work_plan.opens_on' => 'wednesday',
            'work_plan.opens_at' => '09:00',
            'work_plan.locks_on' => 'thursday',
            'work_plan.locks_at' => '17:00',
        ]);

        $this->assertFalse(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-18 08:59:00')));
        $this->assertTrue(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-18 09:00:00')));
        $this->assertTrue(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-19 16:59:00')));
        $this->assertFalse(WorkPlanSetting::isWithinEditingWindow(Carbon::parse('2026-11-19 17:00:00')));
    }

    public function test_admin_may_override_after_the_lock_while_staff_are_blocked(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $task = $this->task($staff, $admin, '2026-11-09');

        Carbon::setTestNow('2026-11-16 12:00:00'); // Monday noon lock

        // Admin and Supervisor may still edit any employee's plan.
        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-17',
                'custom_title' => 'Admin override task',
            ])
            ->assertSessionHas('status', 'Work plan task created successfully.');

        $this->actingAs($admin)
            ->put(route('admin.daily-work-plan.update', $task), [
                'custom_title' => 'Admin override update',
            ])
            ->assertSessionHas('status', 'Work plan task updated successfully.');

        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.sync-day'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-17',
                'legend_codes' => ['A'],
            ])
            ->assertSessionHas('status', 'Daily work plan codes updated.');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'work_plan.override_edit',
        ]);

        // Staff may not plan outside the window.
        $this->actingAs($staff)
            ->post(route('admin.daily-work-plan.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-18',
                'custom_title' => 'Staff blocked task',
            ])
            ->assertForbidden();

        // Staff may still record accomplishments while planning is locked.
        $this->actingAs($staff)
            ->put(route('work-plan.accomplishment.update', $task), [
                'status' => WorkPlanTask::STATUS_COMPLETED,
                'completion_notes' => 'Recorded while planning is locked.',
            ])
            ->assertSessionHas('status', 'Accomplishment saved.');

        $this->assertDatabaseHas('work_plan_tasks', [
            'id' => $task->id,
            'status' => WorkPlanTask::STATUS_COMPLETED,
        ]);
    }

    public function test_staff_may_edit_their_own_plan_only_while_the_window_is_open(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        // Friday: the window is open, so staff may edit their own row.
        $this->actingAs($staff)
            ->post(route('admin.daily-work-plan.sync-day'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-13',
                'legend_codes' => ['A'],
            ])
            ->assertSessionHas('status', 'Daily work plan codes updated.');

        // But not another employee's row.
        $this->actingAs($staff)
            ->post(route('admin.daily-work-plan.sync-day'), [
                'user_id' => $other->id,
                'work_date' => '2026-11-13',
                'legend_codes' => ['A'],
            ])
            ->assertForbidden();

        Carbon::setTestNow('2026-11-16 12:00:00');

        $this->actingAs($staff)
            ->post(route('admin.daily-work-plan.sync-day'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-16',
                'legend_codes' => ['A'],
            ])
            ->assertForbidden();
    }

    public function test_details_are_required_before_completing_t_or_t8_tasks(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('T', 'Other (specify in remarks)', $admin)->update(['requires_details' => true]);

        $task = $this->task($staff, $admin, '2026-11-09', ['legend_code' => 'T']);

        $this->actingAs($staff)
            ->put(route('work-plan.accomplishment.update', $task), [
                'status' => WorkPlanTask::STATUS_COMPLETED,
            ])
            ->assertSessionHasErrors('details');

        $this->actingAs($staff)
            ->put(route('work-plan.accomplishment.update', $task), [
                'status' => WorkPlanTask::STATUS_COMPLETED,
                'details' => 'Called the client and filed the request.',
            ])
            ->assertSessionHas('status', 'Accomplishment saved.');

        $this->assertDatabaseHas('work_plan_tasks', [
            'id' => $task->id,
            'status' => WorkPlanTask::STATUS_COMPLETED,
            'details' => 'Called the client and filed the request.',
        ]);
    }

    public function test_in_progress_counts_as_incomplete_and_s_is_excluded_from_scoring(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $this->legend('S', 'Rest Day / Leave / Holiday', $admin)->update(['is_non_working' => true]);

        $this->task($staff, $admin, '2026-11-09', ['status' => WorkPlanTask::STATUS_COMPLETED]);
        $this->task($staff, $admin, '2026-11-10', ['status' => WorkPlanTask::STATUS_IN_PROGRESS]);
        $this->task($staff, $admin, '2026-11-11', ['legend_code' => 'S', 'status' => WorkPlanTask::STATUS_PENDING]);

        $score = WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START);

        $this->assertSame(2, $score['eligible_planned_tasks']);
        $this->assertSame(1, $score['incomplete_tasks']);
        $this->assertSame(45.0, $score['planned_score']);
    }

    public function test_planning_write_is_allowed_at_the_monday_boundary_before_noon(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        Carbon::setTestNow('2026-11-16 11:59:00');

        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-17',
                'custom_title' => 'Allowed at 11:59',
            ])
            ->assertSessionHas('status', 'Work plan task created successfully.');
    }

    public function test_staff_can_record_accomplishment_and_log_resolve_problems(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $task = $this->task($staff, $admin);

        $this->actingAs($staff)
            ->get(route('work-plan.accomplishment'))
            ->assertOk();

        $this->actingAs($staff)
            ->put(route('work-plan.accomplishment.update', $task), [
                'status' => WorkPlanTask::STATUS_COMPLETED,
                'completion_notes' => 'COR released to client.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Accomplishment saved.');

        $this->assertDatabaseHas('work_plan_tasks', [
            'id' => $task->id,
            'status' => WorkPlanTask::STATUS_COMPLETED,
            'completion_notes' => 'COR released to client.',
            'completed_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->post(route('work-plan.accomplishment.problems.store', $task), [
                'description' => 'Client TIN mismatch',
                'proposed_solution' => 'Request corrected BIR form',
            ])
            ->assertRedirect();

        $problem = WorkPlanTaskProblem::query()->firstOrFail();

        $this->actingAs($staff)
            ->patch(route('work-plan.accomplishment.problems.resolve', [$task, $problem]), [
                'proposed_solution' => 'Corrected form received',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('work_plan_task_problems', [
            'id' => $problem->id,
            'status' => WorkPlanTaskProblem::STATUS_RESOLVED,
            'resolved_by' => $staff->id,
        ]);
    }

    public function test_staff_cannot_update_another_employees_task(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $owner = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $task = $this->task($owner, $admin);

        $this->actingAs($other)
            ->put(route('work-plan.accomplishment.update', $task), [
                'status' => WorkPlanTask::STATUS_COMPLETED,
            ])
            ->assertForbidden();
    }

    public function test_staff_queries_are_scoped_to_their_own_records(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        $own = $this->task($staff, $admin, '2026-11-09', ['custom_title' => 'Own visible task']);
        $foreign = $this->task($other, $admin, '2026-11-09', ['custom_title' => 'Foreign hidden task']);

        $visibleIds = WorkPlanTask::query()->visibleTo($staff)->pluck('id')->all();
        $this->assertContains($own->id, $visibleIds);
        $this->assertNotContains($foreign->id, $visibleIds);

        $this->actingAs($staff)
            ->get(route('work-plan.accomplishment'))
            ->assertOk()
            ->assertSee('Own visible task')
            ->assertDontSee('Foreign hidden task');
    }

    public function test_problem_solving_points_are_awarded_only_by_admin_or_supervisor_evaluation(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        $task = $this->task($staff, $admin, '2026-11-09', ['status' => WorkPlanTask::STATUS_COMPLETED]);

        WorkPlanTaskProblem::create([
            'work_plan_task_id' => $task->id,
            'user_id' => $staff->id,
            'description' => 'Missing receipt',
            'proposed_solution' => 'Requested copy',
            'status' => WorkPlanTaskProblem::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $staff->id,
        ]);

        $score = WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START);
        $this->assertSame(90.0, $score['planned_score']);
        $this->assertFalse($score['problem_evaluated']);
        $this->assertSame(0, $score['problem_score']);
        $this->assertNull($score['overall_score']);
        $this->assertSame(90.0, $score['provisional_score']);
        $this->assertSame(WorkPlanScoringService::STATE_PENDING_EVALUATION, $score['score_state']);

        $this->actingAs($staff)
            ->post(route('work-plan.problem-evaluation.store'), [
                'user_id' => $staff->id,
                'week_start' => self::WEEK_START,
                'problem_solving_score' => 10,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('work-plan.problem-evaluation.store'), [
                'user_id' => $staff->id,
                'week_start' => self::WEEK_START,
                'problem_solving_score' => 9,
            ])
            ->assertSessionHasErrors('problem_solving_score');

        $this->actingAs($supervisor)
            ->post(route('work-plan.problem-evaluation.store'), [
                'user_id' => $staff->id,
                'week_start' => self::WEEK_START,
                'problem_solving_score' => 7,
                'notes' => 'Handled follow-ups well.',
            ])
            ->assertSessionHas('status', 'Problem-solving score saved.');

        $evaluation = WorkPlanWeeklyEvaluation::forUserWeek($staff->id, self::WEEK_START);
        $this->assertSame(7, $evaluation->problem_solving_score);

        $score = WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START);
        $this->assertTrue($score['problem_evaluated']);
        $this->assertSame(7, $score['problem_score']);
        $this->assertSame(97.0, $score['overall_score']);
    }

    public function test_weekly_score_is_a_weighted_weekly_formula_not_a_sum_of_daily_scores(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        foreach (range(1, 3) as $index) {
            $this->task($staff, $admin, '2026-11-09', ['status' => WorkPlanTask::STATUS_COMPLETED]);
        }
        $this->task($staff, $admin, '2026-11-10');

        $score = WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START);

        // 3 of 4 eligible tasks = 75% * 90 = 67.5 points. A daily average
        // (Monday 100%, Tuesday 0%) would incorrectly produce 45 points.
        $this->assertSame(4, $score['eligible_planned_tasks']);
        $this->assertSame(3, $score['completed_tasks']);
        $this->assertSame(67.5, $score['planned_score']);
        $this->assertNull($score['overall_score']);
        $this->assertSame(67.5, $score['provisional_score']);
        $this->assertNotSame(45.0, $score['planned_score']);

        WorkPlanWeeklyEvaluation::create([
            'user_id' => $staff->id,
            'week_start' => self::WEEK_START,
            'problem_solving_score' => 10,
            'evaluated_by' => $admin->id,
            'evaluated_at' => now(),
        ]);

        $this->assertSame(77.5, WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START)['overall_score']);
    }

    public function test_on_hold_tasks_count_as_incomplete_and_cancelled_tasks_are_excluded(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');

        $this->task($staff, $admin, '2026-11-09', ['status' => WorkPlanTask::STATUS_COMPLETED]);
        $this->task($staff, $admin, '2026-11-10', ['status' => WorkPlanTask::STATUS_ON_HOLD]);
        $this->task($staff, $admin, '2026-11-11', ['status' => WorkPlanTask::STATUS_CANCELLED]);

        $score = WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START);

        $this->assertSame(2, $score['eligible_planned_tasks']);
        $this->assertSame(1, $score['completed_tasks']);
        $this->assertSame(1, $score['incomplete_tasks']);
        $this->assertSame(1, $score['cancelled_tasks']);
        $this->assertSame(45.0, $score['planned_score']);
    }

    public function test_additional_urgent_activities_are_separate_from_planned_tasks_and_rated(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $this->legend('A');

        $this->task($staff, $admin, '2026-11-09');

        $this->actingAs($staff)
            ->post(route('work-plan.additional.store'), [
                'work_date' => '2026-11-09',
                'custom_title' => 'Emergency bank run',
                'description' => 'Urgent client deposit',
            ])
            ->assertSessionHas('status', 'Additional/urgent activity logged.');

        $additional = WorkPlanTask::query()->where('is_additional', true)->firstOrFail();
        $this->assertSame($staff->id, $additional->user_id);

        $score = WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START);
        $this->assertSame(1, $score['planned_tasks']);
        $this->assertSame(1, $score['additional_tasks']);
        $this->assertSame(0.0, $score['planned_score']);

        $this->actingAs($staff)
            ->post(route('work-plan.additional.rate', $additional), ['additional_rating' => 9])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('work-plan.additional.rate', $additional), ['additional_rating' => 11])
            ->assertSessionHasErrors('additional_rating');

        $this->actingAs($supervisor)
            ->post(route('work-plan.additional.rate', $additional), ['additional_rating' => 9])
            ->assertSessionHas('status', 'Additional activity rating saved.');

        $additional->refresh();
        $this->assertSame(9, $additional->additional_rating);
        $this->assertSame($supervisor->id, $additional->additional_rated_by);

        $score = WorkPlanScoringService::forUserWeek($staff->id, self::WEEK_START);
        $this->assertSame(1, $score['additional_tasks']);
        $this->assertSame(1, $score['additional_rated_tasks']);
        $this->assertSame(9.0, $score['additional_rating_average']);
        $this->assertSame(0.0, $score['planned_score']);
    }

    public function test_task_carry_over_preserves_the_original_target_date_and_history(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $task = $this->task($staff, $admin, '2026-11-09');

        $this->actingAs($admin)
            ->put(route('admin.daily-work-plan.update', $task), [
                'work_date' => '2026-11-10',
                'change_reason' => 'Client moved the appointment',
            ])
            ->assertSessionHas('status', 'Work plan task updated successfully.');

        $task->refresh();
        $this->assertSame('2026-11-10', $task->work_date->toDateString());
        $this->assertSame('2026-11-09', $task->original_work_date->toDateString());
        $this->assertSame($admin->id, $task->carried_over_by);

        $this->actingAs($admin)
            ->put(route('admin.daily-work-plan.update', $task), [
                'work_date' => '2026-11-11',
            ])
            ->assertSessionHas('status', 'Work plan task updated successfully.');

        $task->refresh();
        $this->assertSame('2026-11-11', $task->work_date->toDateString());
        $this->assertSame('2026-11-09', $task->original_work_date->toDateString());

        $carryOvers = WorkPlanAudit::query()
            ->where('work_plan_task_id', $task->id)
            ->where('action', 'carried_over')
            ->get();

        $this->assertCount(2, $carryOvers);
        $this->assertSame('2026-11-09', $carryOvers->first()->previous_values['work_date']);
    }

    public function test_grid_renders_chips_searchable_popover_and_more_indicator(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $this->legend('B', 'Secure New Service Invoice');
        $this->legend('C', 'Secure New Sales Invoice');
        $this->legend('T1', 'Priority / Rush Client');

        foreach (['A', 'B', 'C', 'T1'] as $code) {
            $this->task($staff, $admin, '2026-11-10', ['legend_code' => $code]);
        }

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.index', ['week_start' => self::WEEK_START]))
            ->assertOk()
            ->assertSee('data-wp-picker', false)
            ->assertSee('data-wp-picker-toggle', false)
            ->assertSee('data-wp-search', false)
            ->assertSee('wp-chip', false)
            ->assertSee('+1 more')
            ->assertSee('data-bs-target="#editTaskModal"', false);
    }

    public function test_sync_day_replaces_codes_and_keeps_completed_history(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $this->legend('B', 'Secure New Service Invoice');
        $this->legend('C', 'Secure New Sales Invoice');

        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.sync-day'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-10',
                'legend_codes' => ['A', 'B'],
            ])
            ->assertSessionHas('status', 'Daily work plan codes updated.');

        $this->assertSame(2, WorkPlanTask::query()->where('user_id', $staff->id)->count());

        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.sync-day'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-10',
                'legend_codes' => ['B', 'C'],
            ])
            ->assertSessionHas('status', 'Daily work plan codes updated.');

        $this->assertDatabaseHas('work_plan_tasks', [
            'user_id' => $staff->id,
            'legend_code' => 'A',
            'status' => WorkPlanTask::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('work_plan_tasks', [
            'user_id' => $staff->id,
            'legend_code' => 'B',
            'status' => WorkPlanTask::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('work_plan_tasks', [
            'user_id' => $staff->id,
            'legend_code' => 'C',
            'status' => WorkPlanTask::STATUS_PENDING,
        ]);

        $completed = WorkPlanTask::query()->where('legend_code', 'B')->firstOrFail();
        $completed->update(['status' => WorkPlanTask::STATUS_COMPLETED]);

        $this->actingAs($admin)
            ->post(route('admin.daily-work-plan.sync-day'), [
                'user_id' => $staff->id,
                'work_date' => '2026-11-10',
                'legend_codes' => ['C'],
            ])
            ->assertSessionHas('status', 'Daily work plan codes updated.');

        $this->assertDatabaseHas('work_plan_tasks', [
            'id' => $completed->id,
            'status' => WorkPlanTask::STATUS_COMPLETED,
        ]);
    }

    public function test_incomplete_and_performance_pages_respect_roles(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $this->legend('A');
        $this->task($staff, $admin, '2026-11-09');
        $this->task($staff, $admin, '2026-11-10', ['status' => WorkPlanTask::STATUS_ON_HOLD]);

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.incomplete', ['week_start' => self::WEEK_START]))
            ->assertOk()
            ->assertSee('Incomplete Tasks')
            ->assertSee('On Hold');

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.performance', ['week_start' => self::WEEK_START]))
            ->assertOk()
            ->assertSee('Weekly Performance Summary')
            ->assertSee($staff->name);

        $this->actingAs($supervisor)
            ->get(route('work-plan.performance', ['week_start' => self::WEEK_START]))
            ->assertOk()
            ->assertSee('Problem-solving evaluation');

        $this->actingAs($supervisor)
            ->get(route('admin.daily-work-plan.incomplete', ['week_start' => self::WEEK_START]))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('work-plan.performance', ['week_start' => self::WEEK_START]))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('admin.daily-work-plan.incomplete', ['week_start' => self::WEEK_START]))
            ->assertForbidden();
    }

    public function test_work_plan_grid_renders_tasks_grouped_by_date(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $this->task($staff, $admin, '2026-11-10');

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.index', ['week_start' => self::WEEK_START]))
            ->assertOk()
            ->assertSee('data-user-id="'.$staff->id.'" data-date="2026-11-10"', false)
            ->assertSee('wp-cell-has-tasks', false)
            ->assertSee('data-bs-target="#editTaskModal"', false);
    }

    public function test_incomplete_summary_includes_on_hold_tasks(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $this->legend('A');
        $this->task($staff, $admin, '2026-11-09', ['status' => WorkPlanTask::STATUS_ON_HOLD]);

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.incomplete-summary', [
                'user_id' => $staff->id,
                'week_start' => self::WEEK_START,
            ]))
            ->assertOk()
            ->assertJsonPath('total_incomplete', 1)
            ->assertJsonPath('details.0.legend_code', 'A');
    }
}
