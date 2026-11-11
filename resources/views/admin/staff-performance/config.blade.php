@extends('layouts.dashboard')

@section('title', 'Scoring Configuration — Egliane Accounting Services')

@section('content')
    {{-- Page Header --}}
    <header class="eas-page-head">
        <div class="eas-head-main">
            <div class="eas-head-left">
                <span class="eas-eyebrow">Staff Performance</span>
                <h1 class="eas-head-title">Scoring Configuration</h1>
                <p class="eas-head-sub">Configure evaluation weights, thresholds, and contribution points for staff performance scoring.</p>
            </div>
        </div>
        <div class="eas-head-actions">
            <a href="{{ route('admin.staff-performance.index') }}" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Dashboard
            </a>
        </div>
    </header>

    {{-- Scoring Weights --}}
    <div class="eas-card">
        <div class="eas-card-head">
            <h2 class="eas-card-title">Evaluation Weights</h2>
            <p class="eas-card-count">Total must equal 100%</p>
        </div>
        <div class="eas-card-body">
            <form method="POST" action="{{ route('admin.staff-performance.config.update') }}">
                @csrf
                <div class="eas-form-grid eas-form-grid-two">
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="quality_weight">Accomplishment Quality</span></label>
                        <input class="eas-form-control" id="quality_weight" name="quality_weight" type="number" min="0" max="100" step="0.1" value="{{ old('quality_weight', $config['quality_weight']) }}" required>
                        <div class="eas-form-hint">Weight: {{ $config['quality_weight'] }}%</div>
                        @error('quality_weight')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="timeliness_weight">Timeliness</label>
                        <input class="eas-form-control" id="timeliness_weight" name="timeliness_weight" type="number" min="0" max="100" step="0.1" value="{{ old('timeliness_weight', $config['timeliness_weight']) }}" required>
                        <div class="eas-form-hint">Weight: {{ $config['timeliness_weight'] }}%</div>
                        @error('timeliness_weight')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="responsiveness_weight">Immediate Responsiveness</label>
                        <input class="eas-form-control" id="responsiveness_weight" name="responsiveness_weight" type="number" min="0" max="100" step="0.1" value="{{ old('responsiveness_weight', $config['responsiveness_weight']) }}" required>
                        <div class="eas-form-hint">Weight: {{ $config['responsiveness_weight'] }}%</div>
                        @error('responsiveness_weight')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="client_feedback_weight">Client Feedback</label>
                        <input class="eas-form-control" id="client_feedback_weight" name="client_feedback_weight" type="number" min="0" max="100" step="0.1" value="{{ old('client_feedback_weight', $config['client_feedback_weight']) }}" required>
                        <div class="eas-form-hint">Weight: {{ $config['client_feedback_weight'] }}%</div>
                        @error('client_feedback_weight')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="eas-form-actions">
                    <button type="submit" class="btn btn-primary">Save Weights</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Thresholds --}}
    <div class="eas-card">
        <div class="eas-card-head">
            <h2 class="eas-card-title">Threshold Configuration</h2>
        </div>
        <div class="eas-card-body">
            <form method="POST" action="{{ route('admin.staff-performance.config.update') }}">
                @csrf
                <div class="eas-form-grid eas-form-grid-two">
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="timeliness_on_time">Timeliness: On Time</label>
                        <input class="eas-form-control" id="timeliness_on_time" name="timeliness_on_time" type="number" min="0" max="100" value="{{ old('timeliness_on_time', $config['timeliness_on_time']) }}" required>
                        <div class="eas-form-hint">Score for on-time submission</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="timeliness_1_day_late">Timeliness: 1 Day Late</label>
                        <input class="eas-form-control" id="timeliness_1_day_late" name="timeliness_1_day_late" type="number" min="0" max="100" value="{{ old('timeliness_1_day_late', $config['timeliness_1_day_late']) }}" required>
                        <div class="eas-form-hint">Score for 1 day late</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="timeliness_2_days_late">Timeliness: 2 Days Late</label>
                        <input class="eas-form-control" id="timeliness_2_days_late" name="timeliness_2_days_late" type="number" min="0" max="100" value="{{ old('timeliness_2_days_late', $config['timeliness_2_days_late']) }}" required>
                        <div class="eas-form-hint">Score for 2 days late</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="timeliness_3_days_late">Timeliness: 3 Days Late</label>
                        <input class="eas-form-control" id="timeliness_3_days_late" name="timeliness_3_days_late" type="number" min="0" max="100" value="{{ old('timeliness_3_days_late', $config['timeliness_3_days_late']) }}" required>
                        <div class="eas-form-hint">Score for 3 days late</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="timeliness_4_days_late">Timeliness: 4 Days Late</label>
                        <input class="eas-form-control" id="timeliness_4_days_late" name="timeliness_4_days_late" type="number" min="0" max="100" value="{{ old('timeliness_4_days_late', $config['timeliness_4_days_late']) }}" required>
                        <div class="eas-form-hint">Score for 4 days late</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="timeliness_5_days_late">Timeliness: 5+ Days Late</label>
                        <input class="eas-form-control" id="timeliness_5_days_late" name="timeliness_5_days_late" type="number" min="0" max="100" value="{{ old('timeliness_5_days_late', $config['timeliness_5_days_late']) }}" required>
                        <div class="eas-form-hint">Score for 5+ days late</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="responsiveness_1_hour">Responsiveness: Within 1 Hour</label>
                        <input class="eas-form-control" id="responsiveness_1_hour" name="responsiveness_1_hour" type="number" min="0" max="100" value="{{ old('responsiveness_1_hour', $config['responsiveness_1_hour']) }}" required>
                        <div class="eas-form-hint">Score for response within 1 hour</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="responsiveness_4_hours">Responsiveness: Within 4 Hours</label>
                        <input class="eas-form-control" id="responsiveness_4_hours" name="responsiveness_4_hours" type="number" min="0" max="100" value="{{ old('responsiveness_4_hours', $config['responsiveness_4_hours']) }}" required>
                        <div class="eas-form-hint">Score for response within 4 hours</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="responsiveness_8_hours">Responsiveness: Within 8 Hours</label>
                        <input class="eas-form-control" id="responsiveness_8_hours" name="responsiveness_8_hours" type="number" min="0" max="100" value="{{ old('responsiveness_8_hours', $config['responsiveness_8_hours']) }}" required>
                        <div class="eas-form-hint">Score for response within 8 hours</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="responsiveness_24_hours">Responsiveness: Within 24 Hours</label>
                        <input class="eas-form-control" id="responsiveness_24_hours" name="responsiveness_24_hours" type="number" min="0" max="100" value="{{ old('responsiveness_24_hours', $config['responsiveness_24_hours']) }}" required>
                        <div class="eas-form-hint">Score for response within 24 hours</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="responsiveness_48_hours">Responsiveness: Within 48 Hours</label>
                        <input class="eas-form-control" id="responsiveness_48_hours" name="responsiveness_48_hours" type="number" min="0" max="100" value="{{ old('responsiveness_48_hours', $config['responsiveness_48_hours']) }}" required>
                        <div class="eas-form-hint">Score for response within 48 hours</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="responsiveness_48_hours_plus">Responsiveness: 48+ Hours</label>
                        <input class="eas-form-control" id="responsiveness_48_hours_plus" name="responsiveness_48_hours_plus" type="number" min="0" max="100" value="{{ old('responsiveness_48_hours_plus', $config['responsiveness_48_hours_plus']) }}" required>
                        <div class="eas-form-hint">Score for response after 48 hours</div>
                    </div>
                </div>
                <div class="eas-form-actions">
                    <button type="submit" class="btn btn-primary">Save Thresholds</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Contribution Points --}}
    <div class="eas-card">
        <div class="eas-card-head">
            <h2 class="eas-card-title">Contribution Points</h2>
        </div>
        <div class="eas-card-body">
            <form method="POST" action="{{ route('admin.staff-performance.config.update') }}">
                @csrf
                <div class="eas-form-grid eas-form-grid-two">
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="contribution_kaizen_submitted">Kaizen Submission</label>
                        <input class="eas-form-control" id="contribution_kaizen_submitted" name="contribution_kaizen_submitted" type="number" min="0" max="100" value="{{ old('contribution_kaizen_submitted', $config['contribution_kaizen_submitted']) }}" required>
                        <div class="eas-form-hint">Points for submitting a Kaizen concern</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="contribution_kaizen_implemented">Kaizen Implementation</label>
                        <input class="eas-form-control" id="contribution_kaizen_implemented" name="contribution_kaizen_implemented" type="number" min="0" max="100" value="{{ old('contribution_kaizen_implemented', $config['contribution_kaizen_implemented']) }}" required>
                        <div class="eas-form-hint">Points for implementing a Kaizen</div>
                    </div>
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="contribution_admin_concern_completed">Admin Concern Completion</label>
                        <input class="eas-form-control" id="contribution_admin_concern_completed" name="contribution_admin_concern_completed" type="number" min="0" max="100" value="{{ old('contribution_admin_concern_completed', $config['contribution_admin_concern_completed']) }}" required>
                        <div class="eas-form-hint">Points for completing an Admin Concern</div>
                    </div>
                </div>
                <div class="eas-form-actions">
                    <button type="submit" class="btn btn-primary">Save Contribution Points</button>
                </div>
            </form>
        </div>
    </div>
@endsection