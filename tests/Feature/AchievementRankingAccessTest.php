<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\DailyJournal;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\AchievementAnalysisService;
use App\Services\AchievementRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AchievementRankingAccessTest extends TestCase
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

    private function user(string $role, string $name): User
    {
        return User::factory()->create([
            'role' => $role,
            'name' => $name,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function assessedJournal(
        User $user,
        string $date,
        int $score,
        string $text,
        string $assessmentStatus = 'verified'
    ): DailyJournal {
        return DailyJournal::create([
            'user_id' => $user->id,
            'required_date' => $date,
            'problems_encountered' => 'Documents arrived late.',
            'achievements' => $text,
            'suggested_solutions' => 'Set an earlier deadline.',
            'status' => DailyJournal::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'achievement_score' => $score,
            'achievement_category' => 'Productivity',
            'achievement_level' => AchievementAnalysisService::levelForScore($score),
            'achievement_explanation' => 'Assessed from submitted evidence.',
            'recognition_tags' => ['Results-oriented'],
            'assessment_source' => 'rule_based',
            'assessment_status' => $assessmentStatus,
            'assessed_at' => now(),
        ]);
    }

    private function assignSupervision(
        User $supervisor,
        User $staff,
        string $supervisorPosition = 'Accounting Supervisor',
        string $staffPosition = 'Account Specialist'
    ): void {
        TeamMember::create([
            'user_id' => $supervisor->id,
            'position' => $supervisorPosition,
            'name' => $supervisor->name,
            'reports_to' => 'Manager',
            'supervises' => $staffPosition,
            'rank' => 'Supervisory',
            'duties' => 'Supervises the assigned team.',
            'sort_order' => 1,
        ]);

        TeamMember::create([
            'user_id' => $staff->id,
            'position' => $staffPosition,
            'name' => $staff->name,
            'reports_to' => $supervisorPosition,
            'rank' => 'Rank and File',
            'duties' => 'Handles assigned client work.',
            'sort_order' => 2,
        ]);
    }

    private function rankings(string $start, string $end, ?User $viewer = null)
    {
        return app(AchievementRankingService::class)->rankings(
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->endOfDay(),
            $viewer
        );
    }

    /** @test */
    public function rankings_respect_today_week_and_month_periods()
    {
        $todayStaff = $this->user(User::ROLE_STAFF, 'Today Staff');
        $weekStaff = $this->user(User::ROLE_STAFF, 'Week Staff');
        $lastMonthStaff = $this->user(User::ROLE_STAFF, 'Last Month Staff');

        $this->assessedJournal($todayStaff, '2026-10-05', 80, 'Completed the daily reconciliation.');
        $this->assessedJournal($weekStaff, '2026-10-02', 90, 'Completed the weekly filing.');
        $this->assessedJournal($lastMonthStaff, '2026-09-29', 70, 'Completed the monthly report.');

        $today = $this->rankings('2026-10-05', '2026-10-05')->pluck('user.name')->all();
        $this->assertSame(['Today Staff'], $today);

        $week = $this->rankings('2026-10-05', '2026-10-11')->pluck('user.name')->all();
        $this->assertSame(['Today Staff'], $week);

        $month = $this->rankings('2026-10-01', '2026-10-31')->pluck('user.name')->all();
        $this->assertEqualsCanonicalizing(['Today Staff', 'Week Staff'], $month);
        $this->assertNotContains('Last Month Staff', $month);
    }

    /** @test */
    public function duplicate_accomplishment_text_counts_only_once()
    {
        $duplicating = $this->user(User::ROLE_STAFF, 'Duplicate Staff');
        $consistent = $this->user(User::ROLE_STAFF, 'Consistent Staff');

        $duplicateText = 'Completed the same weekly report and submitted it on time.';

        $this->assessedJournal($duplicating, '2026-10-01', 90, $duplicateText);
        $this->assessedJournal($duplicating, '2026-10-02', 90, $duplicateText);
        $this->assessedJournal($duplicating, '2026-10-05', 90, $duplicateText);
        $this->assessedJournal($consistent, '2026-10-05', 85, 'Completed a different client filing.');

        $rankings = $this->rankings('2026-10-01', '2026-10-31');

        $duplicateRow = $rankings->firstWhere('user.name', 'Duplicate Staff');
        $this->assertNotNull($duplicateRow);
        $this->assertSame(1, $duplicateRow['submitted_count']);
        $this->assertSame(90.0, (float) $duplicateRow['score']);
        $this->assertSame('Duplicate Staff', $rankings->first()['user']->name);
    }

    /** @test */
    public function ranking_order_uses_average_quality_not_entry_volume()
    {
        $volume = $this->user(User::ROLE_STAFF, 'Volume Staff');
        $quality = $this->user(User::ROLE_STAFF, 'Quality Staff');

        $this->assessedJournal($volume, '2026-10-01', 60, 'Completed task one.');
        $this->assessedJournal($volume, '2026-10-02', 60, 'Completed task two.');
        $this->assessedJournal($volume, '2026-10-05', 60, 'Completed task three.');
        $this->assessedJournal($quality, '2026-10-05', 85, 'Completed a verified client filing.');

        $rankings = $this->rankings('2026-10-01', '2026-10-31');

        $this->assertSame('Quality Staff', $rankings->first()['user']->name);
        $this->assertSame(3, $rankings->firstWhere('user.name', 'Volume Staff')['submitted_count']);
    }

    /** @test */
    public function staff_supervisors_and_admins_can_view_rankings_but_clients_cannot()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Staff Viewer');
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Supervisor Viewer');
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Viewer');
        $client = $this->user(User::ROLE_CLIENT, 'Client Viewer');

        $this->assessedJournal($staff, '2026-10-05', 80, 'Completed the daily reconciliation.');

        $this->actingAs($staff)->get(route('daily-journal.achievements'))->assertOk();
        $this->actingAs($supervisor)->get(route('daily-journal.achievements'))->assertOk();
        $this->actingAs($admin)->get(route('daily-journal.achievements'))->assertOk();

        $this->actingAs($client)->get(route('daily-journal.achievements'))->assertForbidden();
    }

    /** @test */
    public function rankings_distinguish_manual_and_automatic_assessments()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Mixed Assessment Staff');

        $this->assessedJournal($staff, '2026-10-04', 70, 'Completed an automatically assessed filing.', 'provisional');
        $this->assessedJournal($staff, '2026-10-05', 90, 'Completed a manually verified filing.', 'verified');

        $row = $this->rankings('2026-10-01', '2026-10-31')->firstWhere('user.name', 'Mixed Assessment Staff');

        $this->assertNotNull($row);
        $this->assertSame(1, $row['verified_count']);
        $this->assertSame(1, $row['provisional_count']);
        $this->assertSame('partial', $row['verification_status']);

        $this->actingAs($staff)
            ->get(route('daily-journal.achievements', ['period' => 'month']))
            ->assertOk()
            ->assertSee('Manual verified')
            ->assertSee('Automatic provisional');
    }

    /** @test */
    public function assessment_page_shows_automatic_and_manual_modes()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Reviewed Staff');
        $journal = $this->assessedJournal($staff, '2026-10-05', 80, 'Completed the daily reconciliation.');

        $this->actingAs($admin)
            ->get(route('daily-journal.show', $journal))
            ->assertOk()
            ->assertSee('Assessment mode')
            ->assertSee('Automatic')
            ->assertSee('Manual')
            ->assertSee('Manual review')
            ->assertSee('Run automatic reassessment');
    }

    /** @test */
    public function clients_cannot_view_or_review_journals()
    {
        $client = $this->user(User::ROLE_CLIENT, 'Blocked Client');
        $staff = $this->user(User::ROLE_STAFF, 'Journal Owner');
        $journal = $this->assessedJournal($staff, '2026-10-05', 75, 'Completed the daily reconciliation.');

        $this->actingAs($client)->get(route('daily-journal.show', $journal))->assertForbidden();
        $this->actingAs($client)->patch(route('daily-journal.assessment.review', $journal), [
            'score' => 100,
            'category' => 'Productivity',
            'explanation' => 'Client should not be able to do this.',
            'tags' => '',
            'reason' => 'Not allowed.',
        ])->assertForbidden();
        $this->actingAs($client)->post(route('daily-journal.assessment.reassess', $journal), [
            'reason' => 'Not allowed.',
        ])->assertForbidden();

        $this->assertSame(75, $journal->fresh()->achievement_score);
    }

    /** @test */
    public function staff_cannot_correct_or_reassess_assessments()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Staff Owner');
        $journal = $this->assessedJournal($staff, '2026-10-05', 70, 'Completed the daily reconciliation.');

        $this->actingAs($staff)->patch(route('daily-journal.assessment.review', $journal), [
            'score' => 100,
            'category' => 'Productivity',
            'explanation' => 'Self-awarded score.',
            'tags' => '',
            'reason' => 'Self review.',
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('daily-journal.assessment.reassess', $journal), [
            'reason' => 'Self reassessment.',
        ])->assertForbidden();

        $journal->refresh();
        $this->assertSame(70, $journal->achievement_score);
        $this->assertSame('verified', $journal->assessment_status);
        $this->assertNull($journal->reviewed_by);
    }

    /** @test */
    public function staff_cannot_inject_assessment_fields_when_submitting()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Honest Staff');

        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => '2026-10-05',
                'problems_encountered' => 'Documents arrived late.',
                'achievements' => 'Completed the daily reconciliation and submitted the report on time.',
                'suggested_solutions' => 'Set an earlier deadline.',
                'achievement_score' => 100,
                'achievement_level' => 'Outstanding',
                'assessment_status' => 'verified',
                'reviewed_by' => $staff->id,
            ])
            ->assertRedirect();

        $journal = DailyJournal::where('user_id', $staff->id)->firstOrFail();

        $this->assertNotSame('verified', $journal->assessment_status);
        $this->assertSame('provisional', $journal->assessment_status);
        $this->assertNull($journal->reviewed_by);
        $this->assertLessThan(100, $journal->achievement_score);
    }

    /** @test */
    public function admins_can_review_any_assessment_while_supervisors_only_review_their_own()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Supervisor Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Staff Owner');

        $staffJournal = $this->assessedJournal($staff, '2026-10-05', 65, 'Completed the daily reconciliation.');
        $supervisorJournal = $this->assessedJournal($supervisor, '2026-10-05', 70, 'Reviewed all team filings.');
        $clientJournal = $this->assessedJournal($staff, '2026-10-02', 60, 'Completed a client filing.');

        $payload = [
            'score' => 82,
            'category' => 'Quality and Accuracy',
            'explanation' => 'Verified against the filed documents.',
            'tags' => 'Quality Focus, Timeliness',
            'reason' => 'Supporting documents confirmed the result.',
        ];

        $this->actingAs($admin)
            ->patch(route('daily-journal.assessment.review', $staffJournal), $payload)
            ->assertRedirect();
        $this->assertSame(82, $staffJournal->fresh()->achievement_score);

        $this->actingAs($supervisor)
            ->patch(route('daily-journal.assessment.review', $supervisorJournal), $payload)
            ->assertRedirect();
        $this->assertSame(82, $supervisorJournal->fresh()->achievement_score);

        $this->actingAs($supervisor)
            ->patch(route('daily-journal.assessment.review', $clientJournal), $payload)
            ->assertForbidden();
        $this->assertSame(60, $clientJournal->fresh()->achievement_score);
    }

    /** @test */
    public function supervisors_review_only_their_assigned_staff_while_admins_review_everyone()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Lorez Ann Perez');
        $assignedStaff = $this->user(User::ROLE_STAFF, 'Archie Medina');
        $unassignedStaff = $this->user(User::ROLE_STAFF, 'Angeli Gabuat');
        $otherSupervisor = $this->user(User::ROLE_SUPERVISOR, 'Supervisor QA');
        $otherStaff = $this->user(User::ROLE_STAFF, 'Diane Mae Alforte');
        $client = $this->user(User::ROLE_CLIENT, 'Client Viewer');

        $this->assignSupervision($supervisor, $assignedStaff);
        $this->assignSupervision($otherSupervisor, $otherStaff, 'Quality Control Supervisor', 'Liaison Officer');

        $assignedJournal = $this->assessedJournal($assignedStaff, '2026-10-05', 65, 'Completed the daily reconciliation.');
        $unassignedJournal = $this->assessedJournal($unassignedStaff, '2026-10-05', 60, 'Completed a separate filing.');
        $otherStaffJournal = $this->assessedJournal($otherStaff, '2026-10-05', 55, 'Handled another team\'s client.');

        $payload = [
            'score' => 82,
            'category' => 'Quality and Accuracy',
            'explanation' => 'Verified against the filed documents.',
            'tags' => 'Quality Focus, Timeliness',
            'reason' => 'Supporting documents confirmed the result.',
        ];

        $this->actingAs($supervisor)
            ->get(route('daily-journal.show', $assignedJournal))
            ->assertOk();
        $this->actingAs($supervisor)
            ->get(route('daily-journal.show', $unassignedJournal))
            ->assertForbidden();
        $this->actingAs($supervisor)
            ->get(route('daily-journal.show', $otherStaffJournal))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->patch(route('daily-journal.assessment.review', $assignedJournal), $payload)
            ->assertRedirect();
        $this->assertSame(82, $assignedJournal->fresh()->achievement_score);

        $this->actingAs($supervisor)
            ->patch(route('daily-journal.assessment.review', $unassignedJournal), $payload)
            ->assertForbidden();
        $this->assertSame(60, $unassignedJournal->fresh()->achievement_score);

        $this->actingAs($supervisor)
            ->patch(route('daily-journal.assessment.review', $otherStaffJournal), $payload)
            ->assertForbidden();
        $this->assertSame(55, $otherStaffJournal->fresh()->achievement_score);

        $this->actingAs($admin)
            ->patch(route('daily-journal.assessment.review', $unassignedJournal), $payload)
            ->assertRedirect();
        $this->assertSame(82, $unassignedJournal->fresh()->achievement_score);

        $this->actingAs($assignedStaff)
            ->patch(route('daily-journal.assessment.review', $assignedJournal), $payload)
            ->assertForbidden();

        $this->actingAs($client)
            ->patch(route('daily-journal.assessment.review', $assignedJournal), $payload)
            ->assertForbidden();
    }

    /** @test */
    public function org_chart_names_link_supervisors_and_staff_when_accounts_are_not_linked()
    {
        $supervisor = $this->user(User::ROLE_SUPERVISOR, 'Lorez Ann Perez');
        $assignedStaff = $this->user(User::ROLE_STAFF, 'Archie Medina');
        $unassignedStaff = $this->user(User::ROLE_STAFF, 'Angeli Gabuat');

        TeamMember::create([
            'position' => 'Accounting Supervisor',
            'name' => 'Lorez',
            'reports_to' => 'Manager',
            'supervises' => 'Bookkeeping Account and Collection Officer',
            'rank' => 'Supervisory',
            'duties' => 'Supervises the assigned team.',
            'sort_order' => 1,
        ]);

        TeamMember::create([
            'position' => 'Bookkeeping Account and Collection Officer',
            'name' => 'Archie',
            'reports_to' => 'Accounting Supervisor',
            'rank' => 'Rank and File',
            'duties' => 'Handles assigned client work.',
            'sort_order' => 2,
        ]);

        $assignedJournal = $this->assessedJournal($assignedStaff, '2026-10-05', 65, 'Completed the daily reconciliation.');
        $unassignedJournal = $this->assessedJournal($unassignedStaff, '2026-10-05', 60, 'Completed a separate filing.');

        $payload = [
            'score' => 82,
            'category' => 'Quality and Accuracy',
            'explanation' => 'Verified against the filed documents.',
            'tags' => 'Quality Focus, Timeliness',
            'reason' => 'Supporting documents confirmed the result.',
        ];

        $this->actingAs($supervisor)
            ->patch(route('daily-journal.assessment.review', $assignedJournal), $payload)
            ->assertRedirect();
        $this->assertSame(82, $assignedJournal->fresh()->achievement_score);

        $this->actingAs($supervisor)
            ->patch(route('daily-journal.assessment.review', $unassignedJournal), $payload)
            ->assertForbidden();
        $this->assertSame(60, $unassignedJournal->fresh()->achievement_score);
    }

    /** @test */
    public function unassessed_legacy_journals_render_safely_and_do_not_appear_in_rankings()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Viewer');
        $staff = $this->user(User::ROLE_STAFF, 'Legacy Staff');

        $legacy = DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => '2026-09-01',
            'problems_encountered' => 'Legacy problem.',
            'achievements' => 'Legacy accomplishment recorded before assessments existed.',
            'suggested_solutions' => 'Legacy solution.',
            'status' => DailyJournal::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('daily-journal.show', $legacy))
            ->assertOk()
            ->assertSee('Not assessed yet');

        $this->assertTrue($this->rankings('2026-09-01', '2026-09-30')->isEmpty());
    }

    /** @test */
    public function rankings_page_shows_a_clear_empty_state_when_no_data_exists()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Staff Viewer');

        $this->actingAs($staff)
            ->get(route('daily-journal.achievements', ['period' => 'today']))
            ->assertOk()
            ->assertSee('No assessed accomplishments for this period');
    }
}
