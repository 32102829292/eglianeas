<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\KaizenConcern;
use App\Models\StaffTaskEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KaizenAchievementContributionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? (ucfirst($role).' Contribution Tester'),
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function submitConcern(User $creator, array $overrides = []): KaizenConcern
    {
        $this->actingAs($creator)->post(route('admin.kaizen-concerns.submit.store'), array_merge([
            'date_identified' => now()->subDay()->toDateString(),
            'challenge' => 'The client filing process needs a checklist.',
            'recommended_solution' => 'Introduce a standard filing checklist.',
            'target_date' => now()->addDays(5)->toDateString(),
        ], $overrides))->assertRedirect();

        return KaizenConcern::latest('id')->firstOrFail();
    }

    public function test_implemented_suggestion_with_deadline_shows_contribution_section(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Angeli Contributor');
        $admin = $this->user(User::ROLE_ADMIN);

        $concern = $this->submitConcern($staff);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->firstOrFail();
        $this->assertTrue($evaluation->isIncludedInScoreSheet());

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Achievement Ranking Contribution')
            ->assertSee($staff->name)
            ->assertSee('ADDED TO SCORE SHEET')
            ->assertSee(number_format((float) $evaluation->score_sheet_weighted_points, 2))
            ->assertSee(number_format((float) $evaluation->timeliness_score, 1).' / 100')
            ->assertSee(number_format((float) $evaluation->immediate_responsiveness_score, 1).' / 100')
            ->assertSee('View Achievement Ranking');
    }

    public function test_suggestion_without_target_date_reports_insufficient_timeliness(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Angeli No Deadline');

        // Simulate record #10: implemented outside the scoring workflow, so no
        // evaluation was ever created and no target date exists.
        $concern = KaizenConcern::create([
            'type' => KaizenConcern::TYPE_EMPLOYEE_SUGGESTION,
            'date_identified' => '2026-11-10',
            'challenge' => 'The client filing process needs a checklist.',
            'recommended_solution' => 'Introduce a standard filing checklist.',
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
            'implementation_date' => '2026-11-11',
            'assigned_staff_id' => $staff->id,
            'created_by' => $staff->id,
        ]);

        $this->assertNull(StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->first());

        $this->actingAs($staff)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Achievement Ranking Contribution')
            ->assertSee($staff->name)
            ->assertSee('PENDING')
            ->assertSee('Not evaluated — no target date was provided.', false)
            ->assertSee('Awaiting verification of assignment and first response timestamps.');
    }

    public function test_pending_suggestion_shows_pending_contribution(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Angeli Pending');
        $concern = $this->submitConcern($staff);

        $this->assertNotNull(StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->first());

        $this->actingAs($staff)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Achievement Ranking Contribution')
            ->assertSee($staff->name)
            ->assertSee('PENDING INTEGRATION');
    }

    public function test_contribution_attribution_follows_the_evaluation_staff(): void
    {
        $creator = $this->user(User::ROLE_STAFF, 'Creator Person');
        $assignee = $this->user(User::ROLE_STAFF, 'Assigned Person');
        $admin = $this->user(User::ROLE_ADMIN);

        $concern = $this->submitConcern($creator);

        // Reassign to another staff member.
        $this->actingAs($admin)
            ->post(route('admin.kaizen-concerns.assign-staff', $concern), ['assigned_staff_id' => $assignee->id])
            ->assertRedirect();

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->firstOrFail();

        // Kaizen scoring attributes the contribution to the submission's creator
        // (the responsible employee recorded on the evaluation), not the assignee.
        $this->assertSame($creator->id, $evaluation->staff_id);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Achievement Ranking Contribution')
            ->assertSee($creator->name);
    }

    public function test_cancelled_suggestion_does_not_claim_integration(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Angeli Cancelled');
        $admin = $this->user(User::ROLE_ADMIN);

        $concern = $this->submitConcern($staff, [
            'status' => KaizenConcern::STATUS_NOT_IMPLEMENTED,
        ]);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Achievement Ranking Contribution')
            ->assertSee($staff->name)
            ->assertDontSee('ADDED TO SCORE SHEET');
    }
}
