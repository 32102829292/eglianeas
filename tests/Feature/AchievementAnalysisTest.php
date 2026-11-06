<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\DailyJournal;
use App\Models\DailyJournalAssessmentRevision;
use App\Models\User;
use App\Services\AchievementAnalysisService;
use App\Services\RuleBasedAchievementAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AchievementAnalysisTest extends TestCase
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

    private function journal(User $user, array $overrides = []): DailyJournal
    {
        return DailyJournal::create(array_merge([
            'user_id' => $user->id,
            'required_date' => '2026-10-05',
            'problems_encountered' => 'Client documents arrived late.',
            'achievements' => 'Completed monthly bookkeeping for 3 clients, reconciled accounts, submitted BIR forms ahead of the deadline and verified zero errors.',
            'suggested_solutions' => 'Ask clients to send documents earlier.',
            'status' => DailyJournal::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ], $overrides));
    }

    /** @test */
    public function submission_automatically_stores_a_valid_assessment()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Maria Santos');

        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => '2026-10-05',
                'problems_encountered' => 'Two clients sent documents late.',
                'achievements' => 'Completed monthly bookkeeping for 3 clients, reconciled accounts, submitted BIR forms ahead of the deadline and verified zero errors.',
                'suggested_solutions' => 'Ask clients to send documents by the 5th.',
            ])
            ->assertRedirect();

        $journal = DailyJournal::where('user_id', $staff->id)->firstOrFail();

        $this->assertNotNull($journal->achievement_score);
        $this->assertGreaterThanOrEqual(0, $journal->achievement_score);
        $this->assertLessThanOrEqual(100, $journal->achievement_score);
        $this->assertContains($journal->achievement_category, config('achievements.categories'));
        $this->assertSame(
            AchievementAnalysisService::levelForScore($journal->achievement_score),
            $journal->achievement_level
        );
        $this->assertNotEmpty($journal->achievement_explanation);
        $this->assertSame('rule_based', $journal->assessment_source);
        $this->assertSame('provisional', $journal->assessment_status);
        $this->assertNotNull($journal->assessed_at);
    }

    /** @test */
    public function score_levels_follow_the_configured_thresholds()
    {
        $this->assertSame('Outstanding', AchievementAnalysisService::levelForScore(90));
        $this->assertSame('Outstanding', AchievementAnalysisService::levelForScore(100));
        $this->assertSame('Excellent', AchievementAnalysisService::levelForScore(80));
        $this->assertSame('Very Good', AchievementAnalysisService::levelForScore(70));
        $this->assertSame('Good', AchievementAnalysisService::levelForScore(60));
        $this->assertSame('Needs Further Development', AchievementAnalysisService::levelForScore(59));
        $this->assertSame('Needs Further Development', AchievementAnalysisService::levelForScore(0));
    }

    /** @test */
    public function meaningful_completed_work_scores_higher_than_a_vague_entry()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Ana Reyes');

        $strong = $this->journal($staff);
        $strongResult = app(RuleBasedAchievementAnalyzer::class)->analyze($strong);

        $vague = $this->journal($staff, [
            'required_date' => '2026-10-02',
            'achievements' => 'Did work.',
            'problems_encountered' => '',
            'suggested_solutions' => '',
        ]);
        $vagueResult = app(RuleBasedAchievementAnalyzer::class)->analyze($vague);

        $this->assertGreaterThan($vagueResult['score'], $strongResult['score']);
        $this->assertGreaterThanOrEqual(75, $strongResult['score']);
        $this->assertLessThanOrEqual(25, $vagueResult['score']);
        $this->assertContains($strongResult['category'], config('achievements.categories'));
        $this->assertStringContainsString('Rule-based assessment', $strongResult['explanation']);
    }

    /** @test */
    public function empty_descriptions_are_handled_fairly()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Empty Staff');

        $journal = $this->journal($staff, [
            'achievements' => '',
            'problems_encountered' => '',
            'suggested_solutions' => '',
        ]);

        $result = app(RuleBasedAchievementAnalyzer::class)->analyze($journal);

        $this->assertLessThanOrEqual(25, $result['score']);
        $this->assertStringContainsString('too brief', $result['explanation']);
        $this->assertSame('Productivity', $result['category']);
    }

    /** @test */
    public function repeated_entries_receive_a_penalty()
    {
        $staff = $this->user(User::ROLE_STAFF, 'Repeat Staff');
        $control = $this->user(User::ROLE_STAFF, 'Control Staff');

        $text = 'Completed the same weekly report and submitted it to the supervisor on time.';

        DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => '2026-10-01',
            'problems_encountered' => '',
            'achievements' => $text,
            'suggested_solutions' => '',
            'status' => DailyJournal::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $repeated = $this->journal($staff, ['achievements' => $text]);
        $fresh = $this->journal($control, ['required_date' => '2026-10-02', 'achievements' => $text]);

        $service = app(AchievementAnalysisService::class);
        $service->analyze($repeated);
        $service->analyze($fresh);

        $this->assertLessThan($fresh->fresh()->achievement_score, $repeated->fresh()->achievement_score);
        $this->assertStringContainsString('repeats recent entries', $repeated->fresh()->achievement_explanation);
    }

    /** @test */
    public function ai_failure_falls_back_to_transparent_rule_based_analysis()
    {
        config([
            'achievements.ai.enabled' => true,
            'achievements.ai.api_key' => 'test-key',
            'achievements.ai.url' => 'https://ai.example.test/v1/chat/completions',
            'achievements.ai.model' => 'test-model',
        ]);

        Http::fake(['*' => Http::response('service unavailable', 503)]);

        $staff = $this->user(User::ROLE_STAFF, 'Fallback Staff');
        $journal = $this->journal($staff);

        $journal = app(AchievementAnalysisService::class)->analyze($journal);

        $this->assertSame('rule_based', $journal->assessment_source);
        $this->assertStringContainsString('AI unavailable', $journal->achievement_explanation);
        $this->assertNotNull($journal->achievement_score);
        Http::assertSentCount(1);
    }

    /** @test */
    public function invalid_ai_responses_are_rejected_and_fall_back()
    {
        config([
            'achievements.ai.enabled' => true,
            'achievements.ai.api_key' => 'test-key',
            'achievements.ai.url' => 'https://ai.example.test/v1/chat/completions',
        ]);

        $invalid = json_encode([
            'score' => 150,
            'category' => 'Productivity',
            'explanation' => 'Out of range score.',
            'tags' => [],
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => $invalid]]],
            ], 200),
        ]);

        $staff = $this->user(User::ROLE_STAFF, 'Invalid AI Staff');
        $journal = $this->journal($staff);

        $journal = app(AchievementAnalysisService::class)->analyze($journal);

        $this->assertSame('rule_based', $journal->assessment_source);
        $this->assertLessThanOrEqual(100, $journal->achievement_score);
        $this->assertStringContainsString('invalid response', $journal->achievement_explanation);
    }

    /** @test */
    public function unchanged_content_is_not_reanalysed()
    {
        config([
            'achievements.ai.enabled' => true,
            'achievements.ai.api_key' => 'test-key',
            'achievements.ai.url' => 'https://ai.example.test/v1/chat/completions',
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 88,
                            'category' => 'Quality and Accuracy',
                            'explanation' => 'Completed and verified work with a concrete result.',
                            'tags' => ['Quality Focus'],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $staff = $this->user(User::ROLE_STAFF, 'Cached Staff');

        $payload = [
            'date' => '2026-10-05',
            'problems_encountered' => 'Documents arrived late.',
            'achievements' => 'Completed bookkeeping for 3 clients and verified zero errors.',
            'suggested_solutions' => 'Set an earlier document deadline.',
        ];

        $this->actingAs($staff)->post(route('daily-journal.store'), $payload)->assertRedirect();
        $this->actingAs($staff)->post(route('daily-journal.store'), $payload)->assertRedirect();

        $this->assertSame('ai', DailyJournal::where('user_id', $staff->id)->firstOrFail()->assessment_source);
        Http::assertSentCount(1);

        $payload['achievements'] = 'Completed bookkeeping for 4 clients and verified zero errors.';
        $this->actingAs($staff)->post(route('daily-journal.store'), $payload)->assertRedirect();

        Http::assertSentCount(2);
    }

    /** @test */
    public function reviewer_correction_is_verified_and_audited()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Reviewed Staff');
        $journal = $this->journal($staff, ['achievement_score' => 70, 'achievement_category' => 'Productivity', 'achievement_level' => 'Very Good', 'assessment_source' => 'rule_based', 'assessment_status' => 'provisional', 'assessed_at' => now()]);

        $this->actingAs($admin)
            ->patch(route('daily-journal.assessment.review', $journal), [
                'score' => 88,
                'category' => 'Teamwork',
                'level' => 'Very Good',
                'explanation' => 'Coordinated with two teammates and completed the shared filing.',
                'tags' => 'Teamwork, Timeliness',
                'reason' => 'The supervisor confirmed the cross-team contribution.',
            ])
            ->assertRedirect();

        $journal->refresh();

        $this->assertSame(88, $journal->achievement_score);
        $this->assertSame('Very Good', $journal->achievement_level);
        $this->assertSame('Teamwork', $journal->achievement_category);
        $this->assertSame('verified', $journal->assessment_status);
        $this->assertSame('reviewer', $journal->assessment_source);
        $this->assertSame('manual', $journal->assessmentMode());
        $this->assertSame($admin->id, $journal->reviewed_by);
        $this->assertSame('The supervisor confirmed the cross-team contribution.', $journal->review_note);
        $this->assertContains('Teamwork', $journal->recognition_tags);

        $revision = DailyJournalAssessmentRevision::where('daily_journal_id', $journal->id)->firstOrFail();
        $this->assertSame('corrected', $revision->action);
        $this->assertSame(70, $revision->previous_score);
        $this->assertSame(88, $revision->new_score);
        $this->assertSame('reviewer', $revision->new_source);
        $this->assertSame('The supervisor confirmed the cross-team contribution.', $revision->reason);
    }

    /** @test */
    public function automatic_analysis_does_not_overwrite_a_manually_verified_assessment()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Reviewed Staff');
        $journal = $this->journal($staff, [
            'achievement_score' => 88,
            'achievement_category' => 'Teamwork',
            'achievement_level' => 'Very Good',
            'achievement_explanation' => 'Manually reviewed and verified.',
            'assessment_source' => 'reviewer',
            'assessment_status' => 'verified',
            'assessed_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => 'Verified against supporting evidence.',
        ]);

        $journal->forceFill(['achievements' => 'Newly edited text should not replace the verified assessment.'])->save();

        app(AchievementAnalysisService::class)->analyze($journal->fresh());

        $journal->refresh();

        $this->assertSame(88, $journal->achievement_score);
        $this->assertSame('Teamwork', $journal->achievement_category);
        $this->assertSame('Very Good', $journal->achievement_level);
        $this->assertSame('Manually reviewed and verified.', $journal->achievement_explanation);
        $this->assertSame('verified', $journal->assessment_status);
        $this->assertSame('reviewer', $journal->assessment_source);
        $this->assertSame($admin->id, $journal->reviewed_by);
    }

    /** @test */
    public function first_manual_assessment_can_omit_a_reason_and_set_the_level()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Legacy Staff');
        $journal = $this->journal($staff);

        $this->actingAs($admin)
            ->patch(route('daily-journal.assessment.review', $journal), [
                'score' => 88,
                'category' => 'Teamwork',
                'level' => 'Very Good',
                'explanation' => 'Coordinated with two teammates and completed the shared filing.',
                'tags' => 'Teamwork',
            ])
            ->assertRedirect();

        $journal->refresh();

        $this->assertSame('verified', $journal->assessment_status);
        $this->assertSame('reviewer', $journal->assessment_source);
        $this->assertSame('Very Good', $journal->achievement_level);
        $this->assertNull($journal->review_note);

        $revision = DailyJournalAssessmentRevision::where('daily_journal_id', $journal->id)->firstOrFail();
        $this->assertSame('corrected', $revision->action);
        $this->assertNull($revision->previous_score);
        $this->assertSame(88, $revision->new_score);
        $this->assertNull($revision->reason);
    }

    /** @test */
    public function manual_override_requires_a_reason()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Reviewed Staff');
        $journal = $this->journal($staff, [
            'achievement_score' => 70,
            'achievement_category' => 'Productivity',
            'achievement_level' => 'Very Good',
            'assessment_source' => 'rule_based',
            'assessment_status' => 'provisional',
        ]);

        $this->actingAs($admin)
            ->patch(route('daily-journal.assessment.review', $journal), [
                'score' => 90,
                'category' => 'Teamwork',
                'explanation' => 'Corrected after reviewing the evidence.',
                'tags' => '',
            ])
            ->assertSessionHasErrors('reason');

        $journal->refresh();

        $this->assertSame(70, $journal->achievement_score);
        $this->assertSame('provisional', $journal->assessment_status);
    }

    /** @test */
    public function reviewer_can_request_a_reassessment_and_it_is_audited()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Reassessed Staff');
        $journal = $this->journal($staff, ['achievement_score' => 92, 'achievement_category' => 'Productivity', 'achievement_level' => 'Outstanding', 'assessment_source' => 'ai', 'assessment_status' => 'verified', 'assessed_at' => now()]);

        $this->actingAs($admin)
            ->post(route('daily-journal.assessment.reassess', $journal), ['reason' => 'Please re-check the evidence.'])
            ->assertRedirect();

        $journal->refresh();

        $this->assertSame('provisional', $journal->assessment_status);
        $this->assertNull($journal->reviewed_by);
        $this->assertDatabaseHas('daily_journal_assessment_revisions', [
            'daily_journal_id' => $journal->id,
            'reviewer_id' => $admin->id,
            'action' => 'reassessed',
            'reason' => 'Please re-check the evidence.',
        ]);
    }

    /** @test */
    public function review_validation_rejects_out_of_range_scores_and_unknown_categories()
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Reviewer');
        $staff = $this->user(User::ROLE_STAFF, 'Validation Staff');
        $journal = $this->journal($staff, ['achievement_score' => 70, 'achievement_category' => 'Productivity', 'achievement_level' => 'Very Good', 'assessment_source' => 'rule_based', 'assessment_status' => 'provisional']);

        $this->actingAs($admin)
            ->patch(route('daily-journal.assessment.review', $journal), [
                'score' => 150,
                'category' => 'Not A Category',
                'level' => 'Not A Level',
                'explanation' => 'Test',
                'tags' => '',
                'reason' => 'Test',
            ])
            ->assertSessionHasErrors(['score', 'category', 'level']);

        $this->assertSame(70, $journal->fresh()->achievement_score);
    }
}
