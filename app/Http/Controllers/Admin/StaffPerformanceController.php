<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KaizenConcern;
use App\Models\StaffContributionRecord;
use App\Models\StaffEvaluationPeriod;
use App\Models\StaffScoringConfig;
use App\Models\StaffTaskEvaluation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class StaffPerformanceController extends Controller
{
    /**
     * Administrator performance dashboard.
     *
     * Shows all staff evaluations, summary statistics, and filtering.
     */
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can view the performance dashboard.');

        $user = auth()->user();
        $periodId = $request->integer('period_id');
        $staffId = $request->integer('staff_id');
        $grade = $request->string('grade');
        $status = $request->string('status');

        $periods = StaffEvaluationPeriod::active()->orderByDesc('start_date')->get();
        $period = $periodId
            ? StaffEvaluationPeriod::find($periodId)
            : $periods->first();

        $query = StaffTaskEvaluation::query()->notReversed()->with('staff');

        if ($periodId) {
            $query->where('evaluation_period_id', $periodId);
        }

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        if ($grade) {
            $query->where('performance_grade', $grade);
        }

        if ($status) {
            $query->where('evaluation_status', $status);
        }

        $evaluations = $query->orderByDesc('updated_at')->paginate(25);

        // Summary statistics
        $allEvaluations = StaffTaskEvaluation::notReversed()->finalized();
        if ($periodId) {
            $allEvaluations = $allEvaluations->where('evaluation_period_id', $periodId);
        }

        $totalStaff = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at')
            ->count();

        $evaluatedStaff = StaffTaskEvaluation::notReversed()->finalized()
            ->distinct('staff_id')
            ->count('staff_id');

        $avgScore = StaffTaskEvaluation::notReversed()->finalized()
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        $outstandingCount = StaffTaskEvaluation::notReversed()->finalized()
            ->where('overall_score', '>=', 90)
            ->count();

        $pendingCount = StaffTaskEvaluation::notReversed()
            ->where('evaluation_status', '!=', StaffTaskEvaluation::STATUS_FINALIZED)
            ->count();

        $staffList = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $grades = [
            ['value' => 'Outstanding', 'label' => 'Outstanding (95-100)'],
            ['value' => 'Excellent', 'label' => 'Excellent (90-94)'],
            ['value' => 'Very Satisfactory', 'label' => 'Very Satisfactory (85-89)'],
            ['value' => 'Satisfactory', 'label' => 'Satisfactory (80-84)'],
            ['value' => 'Needs Improvement', 'label' => 'Needs Improvement (75-79)'],
            ['value' => 'Unsatisfactory', 'label' => 'Unsatisfactory (<75)'],
        ];

        $statuses = [
            ['value' => StaffTaskEvaluation::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => StaffTaskEvaluation::STATUS_PROVISIONAL, 'label' => 'Provisional'],
            ['value' => StaffTaskEvaluation::STATUS_AWAITING_REVIEW, 'label' => 'Awaiting Review'],
            ['value' => StaffTaskEvaluation::STATUS_FINALIZED, 'label' => 'Finalized'],
            ['value' => StaffTaskEvaluation::STATUS_ADJUSTED, 'label' => 'Adjusted'],
        ];

        return view('admin.staff-performance.index', [
            'evaluations' => $evaluations,
            'periods' => $periods,
            'period' => $period,
            'staffList' => $staffList,
            'selectedStaffId' => $staffId,
            'selectedGrade' => $grade,
            'selectedStatus' => $status,
            'grades' => $grades,
            'statuses' => $statuses,
            'totalStaff' => $totalStaff,
            'evaluatedStaff' => $evaluatedStaff,
            'avgScore' => $avgScore ? round((float) $avgScore, 1) : null,
            'outstandingCount' => $outstandingCount,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Scoring configuration page.
     *
     * Allows admins to configure evaluation weights, thresholds, and contribution points.
     */
    public function config(Request $request): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can configure scoring.');

        if ($request->isMethod('post')) {
            return $this->updateConfig($request);
        }

        $config = [
            'quality_weight' => StaffScoringConfig::get('quality_weight', 50),
            'timeliness_weight' => StaffScoringConfig::get('timeliness_weight', 20),
            'responsiveness_weight' => StaffScoringConfig::get('responsiveness_weight', 20),
            'client_feedback_weight' => StaffScoringConfig::get('client_feedback_weight', 10),
            'timeliness_on_time' => StaffScoringConfig::get('timeliness_on_time', 100),
            'timeliness_1_day_late' => StaffScoringConfig::get('timeliness_1_day_late', 90),
            'timeliness_2_days_late' => StaffScoringConfig::get('timeliness_2_days_late', 80),
            'timeliness_3_days_late' => StaffScoringConfig::get('timeliness_3_days_late', 70),
            'timeliness_4_days_late' => StaffScoringConfig::get('timeliness_4_days_late', 60),
            'timeliness_5_days_late' => StaffScoringConfig::get('timeliness_5_days_late', 50),
            'responsiveness_1_hour' => StaffScoringConfig::get('responsiveness_1_hour', 100),
            'responsiveness_4_hours' => StaffScoringConfig::get('responsiveness_4_hours', 90),
            'responsiveness_8_hours' => StaffScoringConfig::get('responsiveness_8_hours', 80),
            'responsiveness_24_hours' => StaffScoringConfig::get('responsiveness_24_hours', 70),
            'responsiveness_48_hours' => StaffScoringConfig::get('responsiveness_48_hours', 50),
            'responsiveness_48_hours_plus' => StaffScoringConfig::get('responsiveness_48_hours_plus', 30),
            'contribution_kaizen_submitted' => StaffScoringConfig::get('contribution_kaizen_submitted', 2),
            'contribution_kaizen_implemented' => StaffScoringConfig::get('contribution_kaizen_implemented', 3),
            'contribution_admin_concern_completed' => StaffScoringConfig::get('contribution_admin_concern_completed', 3),
        ];

        return view('admin.staff-performance.config', [
            'config' => $config,
        ]);
    }

    /**
     * Update scoring configuration.
     */
    protected function updateConfig(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can configure scoring.');

        $request->validate([
            'quality_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'timeliness_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'responsiveness_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'client_feedback_weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $weights = [
            'quality_weight' => (float) $request->quality_weight,
            'timeliness_weight' => (float) $request->timeliness_weight,
            'responsiveness_weight' => (float) $request->responsiveness_weight,
            'client_feedback_weight' => (float) $request->client_feedback_weight,
        ];

        $totalWeight = array_sum($weights);
        if (abs($totalWeight - 100) > 0.01) {
            return back()
                ->withInput()
                ->withErrors(['quality_weight' => 'Weights must total 100%.']);
        }

        foreach ($weights as $key => $value) {
            StaffScoringConfig::set($key, $value, null, auth()->id());
        }

        // Save other config values
        $numericFields = [
            'timeliness_on_time', 'timeliness_1_day_late', 'timeliness_2_days_late',
            'timeliness_3_days_late', 'timeliness_4_days_late', 'timeliness_5_days_late',
            'responsiveness_1_hour', 'responsiveness_4_hours', 'responsiveness_8_hours',
            'responsiveness_24_hours', 'responsiveness_48_hours', 'responsiveness_48_hours_plus',
            'contribution_kaizen_submitted', 'contribution_kaizen_implemented',
            'contribution_admin_concern_completed',
        ];

        foreach ($numericFields as $field) {
            if ($request->has($field)) {
                StaffScoringConfig::set($field, (float) $request->$field, null, auth()->id());
            }
        }

        ActivityLog::record(
            auth()->user(),
            'admin.staff_scoring_config_updated',
            'Updated scoring configuration.'
        );

        return redirect()->route('admin.staff-performance.config')
            ->with('status', 'Scoring configuration updated.');
    }

    /**
     * View a specific staff member's evaluations.
     */
    public function staff(User $staff): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can view staff evaluations.');

        abort_unless($staff->isOperational(), 403, 'Only staff and supervisor accounts can be evaluated.');

        $evaluations = StaffTaskEvaluation::forStaff($staff->id)
            ->notReversed()
            ->with('staff')
            ->orderByDesc('updated_at')
            ->paginate(25);

        $contributions = StaffContributionRecord::forStaff($staff->id)
            ->awarded()
            ->with('staff')
            ->orderByDesc('awarded_at')
            ->paginate(25);

        $totalPoints = StaffContributionRecord::totalPoints(
            StaffContributionRecord::query(),
            $staff->id
        );

        $avgScore = StaffTaskEvaluation::forStaff($staff->id)
            ->notReversed()
            ->finalized()
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        return view('admin.staff-performance.staff', [
            'staff' => $staff,
            'evaluations' => $evaluations,
            'contributions' => $contributions,
            'totalPoints' => $totalPoints,
            'avgScore' => $avgScore ? round((float) $avgScore, 1) : null,
        ]);
    }
}
