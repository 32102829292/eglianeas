<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\DailyJournal;
use App\Models\KaizenConcern;
use App\Models\StaffEvaluationAudit;
use App\Models\StaffTaskEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreSheetIntegrationBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? (ucfirst($role).' Badge Tester'),
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function submittedConcern(User $staff, string $challenge = 'Badge integration challenge'): KaizenConcern
    {
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => now()->subDay()->toDateString(),
            'challenge' => $challenge,
            'recommended_solution' => 'Standardize the checklist for every client.',
            'target_date' => now()->addDays(5)->toDateString(),
        ])->assertRedirect();

        return KaizenConcern::where('challenge', $challenge)->firstOrFail();
    }

    private function journal(User $staff): DailyJournal
    {
        return DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => now()->toDateString(),
            'problems_encountered' => 'No blockers.',
            'achievements' => 'Completed daily tasks.',
            'suggested_solutions' => 'Continue the current workflow.',
            'status' => DailyJournal::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function test_provisional_evaluation_is_not_claimed_as_added(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->submittedConcern($staff);

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->firstOrFail();

        $this->assertFalse($evaluation->score_sheet_included);
        $this->assertNull($evaluation->score_sheet_added_at);
        $this->assertSame('PENDING INTEGRATION', $evaluation->integrationBadgeLabel());
        $this->assertSame('badge-score-pending', $evaluation->integrationBadgeClass());
    }

    public function test_implementation_links_evaluation_to_score_sheet(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->submittedConcern($staff);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->firstOrFail()->refresh();

        $this->assertTrue($evaluation->score_sheet_included);
        $this->assertNotNull($evaluation->score_sheet_added_at);
        $this->assertSame('quality', $evaluation->score_sheet_criterion);
        $this->assertSame(round((float) $evaluation->overall_score * 0.50, 2), (float) $evaluation->score_sheet_weighted_points);
        $this->assertSame('ADDED TO SCORE SHEET', $evaluation->integrationBadgeLabel());
        $this->assertSame('badge-score-added', $evaluation->integrationBadgeClass());
    }

    public function test_score_sheet_page_lists_kaizen_source_and_weighted_points(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->submittedConcern($staff, 'Score sheet source challenge');
        $journal = $this->journal($staff);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->firstOrFail()->refresh();

        $this->actingAs($staff)
            ->get(route('daily-journal.assessment.score-sheet', $journal))
            ->assertOk()
            ->assertSee('Kaizen Strategy &amp; Admin Concern Sources', false)
            ->assertSee('ADDED TO SCORE SHEET')
            ->assertSee('Score sheet source challenge')
            ->assertSee(number_format((float) $evaluation->score_sheet_weighted_points, 2));
    }

    public function test_admin_reversal_excludes_evaluation_from_score_sheet(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->submittedConcern($staff, 'Reversal challenge');
        $journal = $this->journal($staff);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.kaizen-concerns.evaluation.reverse', $concern), [
                'reason' => 'Implementation evidence was invalid.',
            ])
            ->assertRedirect();

        $evaluation->refresh();

        $this->assertSame(StaffTaskEvaluation::STATUS_REVERSED, $evaluation->evaluation_status);
        $this->assertFalse($evaluation->score_sheet_included);
        $this->assertNotNull($evaluation->score_sheet_reversed_at);
        $this->assertNull($evaluation->score_sheet_weighted_points);
        $this->assertSame('REVERSED / EXCLUDED', $evaluation->integrationBadgeLabel());
        $this->assertSame('badge-score-reversed', $evaluation->integrationBadgeClass());
        $this->assertTrue(
            StaffEvaluationAudit::where('evaluation_id', $evaluation->id)
                ->where('action', 'reversed')
                ->exists()
        );

        $this->actingAs($staff)
            ->get(route('daily-journal.assessment.score-sheet', $journal))
            ->assertOk()
            ->assertSee('REVERSED / EXCLUDED')
            ->assertSee('Implementation evidence was invalid.');
    }

    public function test_reopening_task_removes_it_from_score_sheet(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->submittedConcern($staff, 'Reopen challenge');

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->firstOrFail();
        $this->assertTrue($evaluation->score_sheet_included);

        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'challenge' => $concern->challenge,
            'status' => KaizenConcern::STATUS_PENDING,
            'implementation_date' => null,
            'date_identified' => $concern->date_identified?->toDateString(),
            'recommended_solution' => $concern->recommended_solution,
            'notes' => $concern->notes,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $evaluation->refresh();

        $this->assertSame(StaffTaskEvaluation::STATUS_REVERSED, $evaluation->evaluation_status);
        $this->assertFalse($evaluation->score_sheet_included);
        $this->assertSame('REVERSED / EXCLUDED', $evaluation->integrationBadgeLabel());
    }

    public function test_reassigned_admin_concern_follows_the_new_staff_member(): void
    {
        $staff1 = $this->user(User::ROLE_STAFF, 'First Owner');
        $staff2 = $this->user(User::ROLE_STAFF, 'Second Owner');
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.store'), [
            'date_identified' => now()->subDay()->toDateString(),
            'challenge' => 'Reassignment badge challenge',
            'recommended_solution' => 'Assign the correct owner.',
            'target_date' => now()->addDays(5)->toDateString(),
            'assigned_staff_id' => $staff1->id,
            'status' => KaizenConcern::STATUS_PENDING,
            'notes' => '',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Reassignment badge challenge')->firstOrFail();
        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_ADMIN_CONCERN, $concern->id)->firstOrFail();
        $this->assertSame($staff1->id, $evaluation->staff_id);

        $this->actingAs($admin)
            ->post(route('admin.kaizen-concerns.assign-staff', $concern), [
                'assigned_staff_id' => $staff2->id,
            ])
            ->assertRedirect();

        $evaluation->refresh();
        $this->assertSame($staff2->id, $evaluation->staff_id);
        $this->assertTrue(
            StaffEvaluationAudit::where('evaluation_id', $evaluation->id)
                ->where('action', 'reassigned')
                ->exists()
        );
    }
}
