<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ClientSurveyResponse;
use App\Models\DailyJournal;
use App\Models\KaizenConcern;
use App\Models\PriorityItem;
use App\Models\User;
use App\Services\AchievementAnalysisService;
use App\Services\AchievementCriteriaService;
use App\Services\AchievementRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AchievementCriteriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('supabase');
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00'));
        config(['achievements.ai.enabled' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role = User::ROLE_STAFF, string $name = 'Staff Member'): User
    {
        return User::factory()->create([
            'role' => $role,
            'name' => $name,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function journal(User $user, string $date, int $score, string $text): DailyJournal
    {
        return DailyJournal::create([
            'user_id' => $user->id,
            'required_date' => $date,
            'problems_encountered' => 'None.',
            'achievements' => $text,
            'suggested_solutions' => 'None.',
            'status' => DailyJournal::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'achievement_score' => $score,
            'achievement_category' => 'Productivity',
            'achievement_level' => AchievementAnalysisService::levelForScore($score),
            'achievement_explanation' => 'Assessed.',
            'recognition_tags' => [],
            'assessment_source' => 'rule_based',
            'assessment_status' => 'verified',
            'assessed_at' => now(),
        ]);
    }

    private function priorityItem(User $staff, array $attrs = []): PriorityItem
    {
        return PriorityItem::create(array_merge([
            'task_lesson' => 'Test task',
            'type' => 'todo',
            'description' => 'A test task.',
            'priority' => 'medium',
            'assigned_staff_id' => $staff->id,
            'due_date' => '2026-10-05',
            'status' => 'pending',
            'created_by' => $this->user(User::ROLE_ADMIN, 'Admin')->id,
        ], $attrs));
    }

    private function setCompletedAt(PriorityItem $item, string $date): void
    {
        $item->forceFill(['completed_at' => $date])->save();
    }

    private function kaizenConcern(User $staff, array $attrs = []): KaizenConcern
    {
        return KaizenConcern::create(array_merge([
            'type' => 'admin_concern',
            'date_identified' => '2026-10-01',
            'challenge' => 'A test concern.',
            'recommended_solution' => 'Fix it.',
            'target_date' => '2026-10-05',
            'implementation_date' => null,
            'assigned_staff_id' => $staff->id,
            'status' => 'pending',
            'notes' => '',
            'created_by' => $this->user(User::ROLE_ADMIN, 'Admin')->id,
        ], $attrs));
    }

    private function rankings(string $start, string $end, ?User $viewer = null)
    {
        return app(AchievementRankingService::class)->rankings(
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->endOfDay(),
            $viewer
        );
    }

    // ------------------------------------------------------------------
    // Criterion scoring: Timeliness
    // ------------------------------------------------------------------

    /** @test */
    public function on_time_task_completion_scores_high_timeliness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Punctual Staff');
        $this->priorityItem($staff, [
            'due_date' => '2026-10-05',
            'completed_at' => '2026-10-04 10:00:00', // before deadline
            'status' => PriorityItem::STATUS_COMPLETED,
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNotNull($criteria['timeliness']);
        $this->assertGreaterThanOrEqual(90, (float) $criteria['timeliness']);
        $this->assertSame(1, $criteria['timeliness_meta']['on_time']);
        $this->assertSame(1, $criteria['timeliness_meta']['early']);
    }

    /** @test */
    public function completed_on_deadline_scores_timeliness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'On Deadline Staff');
        $this->priorityItem($staff, [
            'due_date' => '2026-10-05',
            'completed_at' => '2026-10-05 17:00:00', // on deadline
            'status' => PriorityItem::STATUS_COMPLETED,
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNotNull($criteria['timeliness']);
        $this->assertGreaterThanOrEqual(90, (float) $criteria['timeliness']);
    }

    /** @test */
    public function late_task_completion_scores_lower_timeliness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Late Staff');
        $this->priorityItem($staff, [
            'due_date' => '2026-10-01',
            'completed_at' => '2026-10-05 10:00:00', // 4 days late
            'status' => PriorityItem::STATUS_COMPLETED,
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNotNull($criteria['timeliness']);
        $this->assertLessThan(90, (float) $criteria['timeliness']);
    }

    /** @test */
    public function overdue_and_not_completed_scores_zero_timeliness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Overdue Staff');
        $this->priorityItem($staff, [
            'due_date' => '2026-10-01',
            'completed_at' => null,
            'status' => PriorityItem::STATUS_OVERDUE,
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNotNull($criteria['timeliness']);
        $this->assertSame(1, $criteria['timeliness_meta']['overdue_not_completed']);
        $this->assertSame(0, $criteria['timeliness_meta']['on_time_rate']);
    }

    /** @test */
    public function cancelled_or_not_implemented_tasks_are_excluded_from_timeliness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Cancelled Staff');
        $this->kaizenConcern($staff, [
            'target_date' => '2026-10-05',
            'status' => KaizenConcern::STATUS_NOT_IMPLEMENTED,
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        // No evaluable tasks remain.
        $this->assertNull($criteria['timeliness']);
        $this->assertSame(0, $criteria['timeliness_meta']['evaluated']);
    }

    /** @test */
    public function tasks_without_deadline_are_excluded_from_timeliness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'No Deadline Staff');
        $this->priorityItem($staff, [
            'due_date' => null,
            'status' => PriorityItem::STATUS_COMPLETED,
            'completed_at' => '2026-10-05 10:00:00',
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNull($criteria['timeliness']);
        $this->assertSame(0, $criteria['timeliness_meta']['evaluated']);
    }

    // ------------------------------------------------------------------
    // Criterion scoring: Immediate Responsiveness
    // ------------------------------------------------------------------

    /** @test */
    public function immediate_acknowledgment_scores_high_responsiveness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Responsive Staff');
        $this->priorityItem($staff, [
            'created_at' => '2026-10-05 08:00:00',
            'completed_at' => '2026-10-05 09:00:00', // 1 hour later
            'status' => PriorityItem::STATUS_COMPLETED,
            'due_date' => '2026-10-05',
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNotNull($criteria['responsiveness']);
        $this->assertSame(1, $criteria['responsiveness_meta']['prompt']);
        $this->assertSame(0, $criteria['responsiveness_meta']['delayed']);
    }

    /** @test */
    public function delayed_response_scores_lower_responsiveness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Delayed Staff');
        $item = $this->priorityItem($staff, [
            'status' => PriorityItem::STATUS_COMPLETED,
            'due_date' => '2026-10-05',
        ]);
        // created_at is test now (2026-10-05 09:00); completed 2 days later.
        $this->setCompletedAt($item, '2026-10-07');

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNotNull($criteria['responsiveness']);
        $this->assertSame(1, $criteria['responsiveness_meta']['delayed']);
    }

    /** @test */
    public function unanswered_concerns_count_as_unanswered(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Unanswered Staff');
        $this->kaizenConcern($staff, [
            'created_at' => '2026-10-01 08:00:00',
            'target_date' => '2026-10-05',
            'implementation_date' => null,
            'status' => KaizenConcern::STATUS_IN_PROGRESS,
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertSame(1, $criteria['responsiveness_meta']['unanswered']);
        $this->assertSame(0, $criteria['responsiveness_meta']['prompt']);
    }

    // ------------------------------------------------------------------
    // Criterion scoring: Client Feedback
    // ------------------------------------------------------------------

    /** @test */
    public function client_feedback_is_not_attributable_returns_null(): void
    {
        // There is no reliable staff association on ClientSurveyResponse
        // (user_id is the client, not staff), so feedback must never be
        // attributed to an individual staff member.
        $staff = $this->user(User::ROLE_STAFF, 'Feedback Staff');
        $client = $this->user(User::ROLE_CLIENT, 'Client');

        ClientSurveyResponse::create([
            'user_id' => $client->id,
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => 'Great service.',
            'submitted_at' => now(),
        ]);

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNull($criteria['client_feedback']);
        $this->assertSame('No attributable client feedback available.', $criteria['client_feedback_explanation']);
        $this->assertSame(0, $criteria['client_feedback_meta']['positive']);
        $this->assertSame(0, $criteria['client_feedback_meta']['negative']);
        $this->assertFalse($criteria['client_feedback_meta']['attributable']);
    }

    // ------------------------------------------------------------------
    // Missing data handling
    // ------------------------------------------------------------------

    /** @test */
    public function no_data_returns_null_not_zero(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'No Data Staff');

        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $this->assertNull($criteria['quality']);
        $this->assertNull($criteria['timeliness']);
        $this->assertNull($criteria['responsiveness']);
        $this->assertNull($criteria['client_feedback']);
    }

    /** @test */
    public function missing_data_criterion_excluded_from_overall_score(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Partial Data Staff');

        // No criterion has reliable data: client feedback is unattributable,
        // and there are no tasks or journals.
        $criteria = app(AchievementCriteriaService::class)->analyze(
            $staff,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05')
        );

        $combined = app(AchievementCriteriaService::class)->combine($criteria, [
            'quality' => 50, 'timeliness' => 20, 'responsiveness' => 20, 'client_feedback' => 10,
        ]);

        // No criterion has data, so overall score is null (not zero).
        $this->assertNull($combined['overall_score']);
        $this->assertSame([], $combined['weights_used']);
    }

    // ------------------------------------------------------------------
    // Ranking integration
    // ------------------------------------------------------------------

    /** @test */
    public function ranking_rows_include_criterion_scores(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Ranked Staff');
        $this->journal($staff, '2026-10-05', 80, 'Completed daily reconciliation.');
        $this->priorityItem($staff, [
            'due_date' => '2026-10-05',
            'completed_at' => '2026-10-05 09:00:00',
            'status' => PriorityItem::STATUS_COMPLETED,
        ]);

        $row = $this->rankings('2026-10-01', '2026-10-31')->firstWhere('user.name', 'Ranked Staff');

        $this->assertNotNull($row);
        $this->assertArrayHasKey('quality_score', $row);
        $this->assertArrayHasKey('timeliness_score', $row);
        $this->assertArrayHasKey('responsiveness_score', $row);
        $this->assertArrayHasKey('client_feedback_score', $row);
        $this->assertArrayHasKey('overall_score', $row);
        $this->assertArrayHasKey('criteria_explanation', $row);
        $this->assertArrayHasKey('on_time_completion_rate', $row);
        $this->assertArrayHasKey('avg_first_response_hours', $row);
        $this->assertArrayHasKey('positive_feedback_count', $row);
        $this->assertArrayHasKey('negative_feedback_count', $row);

        // Client feedback is never attributable, so it must be null with zero counts.
        $this->assertNull($row['client_feedback_score']);
        $this->assertSame(0, $row['positive_feedback_count']);
        $this->assertSame(0, $row['negative_feedback_count']);
    }

    /** @test */
    public function ranking_sorts_by_overall_score_when_available(): void
    {
        $high = $this->user(User::ROLE_STAFF, 'High Overall Staff');
        $low = $this->user(User::ROLE_STAFF, 'Low Overall Staff');

        $this->journal($high, '2026-10-05', 90, 'Excellent work.');
        $this->journal($low, '2026-10-05', 60, 'Basic work.');

        $rankings = $this->rankings('2026-10-01', '2026-10-31');

        $this->assertSame('High Overall Staff', $rankings->first()['user']->name);
        $this->assertSame('Low Overall Staff', $rankings->last()['user']->name);
    }

    /** @test */
    public function rankings_across_all_periods(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Period Staff');

        $this->journal($staff, '2026-10-05', 80, 'Today work.');
        $this->journal($staff, '2026-10-02', 85, 'Week work.');
        $this->journal($staff, '2026-09-29', 70, 'Last month work.');

        $today = $this->rankings('2026-10-05', '2026-10-05');
        $this->assertSame(1, $today->count());

        $week = $this->rankings('2026-10-05', '2026-10-11');
        $this->assertSame(1, $week->count());

        $month = $this->rankings('2026-10-01', '2026-10-31');
        $this->assertSame(1, $month->count());
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    /** @test */
    public function staff_supervisor_and_admin_can_view_rankings_with_criteria(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Staff Viewer');
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Supervisor Viewer');
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Viewer');
        $client = $this->user(User::ROLE_CLIENT, 'Client Viewer');

        $this->journal($staff, '2026-10-05', 80, 'Completed daily reconciliation.');

        $this->actingAs($staff)->get(route('daily-journal.achievements'))->assertOk()
            ->assertSee('Overall Score')->assertSee('Timeliness')->assertSee('Responsiveness')->assertSee('Client Feedback');
        $this->actingAs($supervisor)->get(route('daily-journal.achievements'))->assertOk();
        $this->actingAs($admin)->get(route('daily-journal.achievements'))->assertOk();

        $this->actingAs($client)->get(route('daily-journal.achievements'))->assertForbidden();
    }
}
