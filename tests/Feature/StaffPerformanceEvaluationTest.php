<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\KaizenConcern;
use App\Models\StaffContributionRecord;
use App\Models\StaffEvaluationPeriod;
use App\Models\StaffScoringConfig;
use App\Models\StaffTaskEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPerformanceEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? (ucfirst($role) . ' Tester'),
            'email' => $role . '.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function concern(?User $creator = null, string $status = KaizenConcern::STATUS_PENDING, ?string $type = KaizenConcern::TYPE_EMPLOYEE_SUGGESTION, ?User $assignee = null): KaizenConcern
    {
        return KaizenConcern::create([
            'type' => $type,
            'date_identified' => now()->subDays(2),
            'challenge' => 'Client documents arrive without a checklist.',
            'recommended_solution' => 'Add a standardized document checklist for every client.',
            'status' => $status,
            'assigned_staff_id' => $assignee?->id,
            'created_by' => ($creator ?? $this->user(User::ROLE_ADMIN))->id,
        ]);
    }

    private function adminConcern(?User $creator = null, string $status = KaizenConcern::STATUS_PENDING, ?User $assignee = null): KaizenConcern
    {
        return KaizenConcern::create([
            'type' => KaizenConcern::TYPE_ADMIN_CONCERN,
            'date_identified' => now()->subDays(2),
            'challenge' => 'Office printer needs maintenance.',
            'recommended_solution' => 'Schedule regular printer maintenance.',
            'status' => $status,
            'assigned_staff_id' => $assignee?->id,
            'target_date' => now()->addDays(5),
            'created_by' => ($creator ?? $this->user(User::ROLE_ADMIN))->id,
        ]);
    }

    public function test_kaizen_submission_creates_evaluation_record()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Test challenge for scoring',
            'recommended_solution' => 'Test solution for scoring',
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Test challenge for scoring')->firstOrFail();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->first();

        $this->assertNotNull($evaluation);
        $this->assertEquals($staff->id, $evaluation->staff_id);
        $this->assertEquals(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $evaluation->source_type);
        $this->assertEquals($concern->id, $evaluation->source_id);
        $this->assertNotNull($evaluation->accomplishment_quality_score);
        $this->assertNotNull($evaluation->overall_score);
        $this->assertEquals(StaffTaskEvaluation::STATUS_PROVISIONAL, $evaluation->evaluation_status);
    }

    public function test_duplicate_evaluation_not_created_on_resubmission()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Duplicate test challenge',
            'recommended_solution' => 'Test solution',
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Duplicate test challenge')->firstOrFail();

        $firstEval = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->first();
        $this->assertNotNull($firstEval);

        // Simulate re-submission of same concern
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Duplicate test challenge',
            'recommended_solution' => 'Test solution',
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $count = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->count();
        $this->assertSame(1, $count, 'Duplicate evaluation should not be created');
    }

    public function test_implementation_updates_evaluation()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Test challenge for scoring',
            'recommended_solution' => 'Test solution for scoring',
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Test challenge for scoring')->firstOrFail();
        $concern->target_date = '2026-10-10';
        $concern->save();

        // First, submission evaluation exists
        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->first();
        $this->assertNotNull($evaluation);

        // Now admin confirms implementation
        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $evaluation->refresh();
        $this->assertNotNull($evaluation->overall_score);
        $this->assertEquals(StaffTaskEvaluation::STATUS_FINALIZED, $evaluation->evaluation_status);
        $this->assertNotNull($evaluation->finalized_at);
        $this->assertNotNull($evaluation->evaluated_by);
    }

    public function test_admin_concern_assignment_creates_pending_evaluation()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Office printer needs maintenance.',
            'recommended_solution' => 'Schedule regular printer maintenance.',
            'target_date' => '2026-10-15',
            'implementation_date' => null,
            'assigned_staff_id' => $staff->id,
            'status' => KaizenConcern::STATUS_PENDING,
            'notes' => '',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Office printer needs maintenance.')->firstOrFail();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_ADMIN_CONCERN, $concern->id)->first();

        $this->assertNotNull($evaluation);
        $this->assertEquals($staff->id, $evaluation->staff_id);
        $this->assertEquals(StaffTaskEvaluation::SOURCE_ADMIN_CONCERN, $evaluation->source_type);
        $this->assertEquals(StaffTaskEvaluation::STATUS_PENDING, $evaluation->evaluation_status);
        $this->assertNull($evaluation->overall_score); // Not yet scored
    }

    public function test_admin_concern_completion_creates_evaluation()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Office printer needs maintenance.',
            'recommended_solution' => 'Schedule regular printer maintenance.',
            'target_date' => '2026-10-15',
            'assigned_staff_id' => $staff->id,
            'status' => KaizenConcern::STATUS_PENDING,
            'notes' => '',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Office printer needs maintenance.')->firstOrFail();

        // Admin confirms implementation
        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'task_lesson' => $concern->challenge,
            'type' => $concern->type,
            'priority' => 'medium',
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
            'implementation_date' => '2026-10-10',
            'assigned_staff_id' => $staff->id,
            'date_identified' => '2026-10-04',
            'recommended_solution' => $concern->recommended_solution,
            'notes' => $concern->notes,
        ])->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_ADMIN_CONCERN, $concern->id)->first();
        $this->assertNotNull($evaluation);
        $this->assertNotNull($evaluation->overall_score);
        $this->assertEquals(StaffTaskEvaluation::STATUS_FINALIZED, $evaluation->evaluation_status);
    }

    public function test_staff_cannot_access_another_employees_evaluation()
    {
        $staff1 = $this->user(User::ROLE_STAFF);
        $staff2 = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);

        $concern = $this->concern($staff1, KaizenConcern::STATUS_PENDING);
        $this->actingAs($staff1)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Staff1 challenge',
            'recommended_solution' => 'Test solution',
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->first();

        // Staff2 should not be able to access staff1's evaluation via the staff dashboard
        $this->actingAs($staff2)->get(route('admin.staff-performance.staff', $staff1))->assertForbidden();

        // Staff2 should not be able to access the evaluation record directly
        $this->actingAs($staff2)->get(route('admin.kaizen-concerns.show', $concern))->assertForbidden();
    }

    public function test_overall_score_calculation()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);

        $concern = $this->concern($staff, KaizenConcern::STATUS_PENDING);
        $concern->target_date = '2026-10-10';
        $concern->save();

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => $concern->challenge,
            'recommended_solution' => $concern->recommended_solution,
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $evaluation = StaffTaskEvaluation::forSource(StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN, $concern->id)->first();

        // Verify all criteria are scored
        $this->assertNotNull($evaluation->accomplishment_quality_score);
        $this->assertNotNull($evaluation->timeliness_score);
        $this->assertNotNull($evaluation->immediate_responsiveness_score);

        // Verify overall score is weighted correctly
        $expected = round(
            ($evaluation->accomplishment_quality_score * 0.50) +
            ($evaluation->timeliness_score * 0.20) +
            ($evaluation->immediate_responsiveness_score * 0.20),
            1
        );
        $this->assertSame($expected, $evaluation->overall_score);
        $this->assertLessThanOrEqual(100, $evaluation->overall_score);
        $this->assertGreaterThanOrEqual(0, $evaluation->overall_score);
    }

    public function test_contribution_points_awarded_on_submission()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Contribution test challenge',
            'recommended_solution' => 'Test solution',
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Contribution test challenge')->firstOrFail();

        $contribution = StaffContributionRecord::where('staff_id', $staff->id)
            ->where('activity_type', StaffContributionRecord::ACTIVITY_KAIZEN_SUBMITTED)
            ->where('source_type', StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN)
            ->where('source_id', $concern->id)
            ->first();

        $this->assertNotNull($contribution);
        $this->assertEquals(2, $contribution->points);
    }

    public function test_contribution_points_not_duplicated()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Dup contribution test',
            'recommended_solution' => 'Test solution',
            'target_date' => '2026-10-10',
        ])->assertRedirect();

        $concern = KaizenConcern::where('challenge', 'Dup contribution test')->firstOrFail();

        $count = StaffContributionRecord::where('staff_id', $staff->id)
            ->where('activity_type', StaffContributionRecord::ACTIVITY_KAIZEN_SUBMITTED)
            ->where('source_type', StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN)
            ->where('source_id', $concern->id)
            ->count();

        $this->assertSame(1, $count, 'Duplicate contribution points should not be awarded');
    }

    public function test_admin_can_access_staff_performance_dashboard()
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin)->get(route('admin.staff-performance.index'))->assertOk();
    }

    public function test_staff_cannot_access_performance_dashboard()
    {
        $staff = $this->user(User::ROLE_STAFF);
        $this->actingAs($staff)->get(route('admin.staff-performance.index'))->assertForbidden();
    }

    public function test_admin_can_access_scoring_config()
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin)->get(route('admin.staff-performance.config'))->assertOk();
    }

    public function test_scoring_config_update()
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin)->post(route('admin.staff-performance.config.update'), [
            'quality_weight' => 40,
            'timeliness_weight' => 30,
            'responsiveness_weight' => 20,
            'client_feedback_weight' => 10,
        ])->assertRedirect();

        $this->assertSame(40, StaffScoringConfig::get('quality_weight'));
        $this->assertSame(30, StaffScoringConfig::get('timeliness_weight'));
        $this->assertSame(20, StaffScoringConfig::get('responsiveness_weight'));
        $this->assertSame(10, StaffScoringConfig::get('client_feedback_weight'));
    }

    public function test_scoring_config_rejects_invalid_weights()
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin)->post(route('admin.staff-performance.config.update'), [
            'quality_weight' => 50,
            'timeliness_weight' => 30,
            'responsiveness_weight' => 30,
            'client_feedback_weight' => 10,
        ])->assertSessionHasErrors('quality_weight');

        // Weights should not have changed
        $this->assertNotSame(50, StaffScoringConfig::get('quality_weight'));
    }
}
