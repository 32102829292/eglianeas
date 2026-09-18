<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Document;
use App\Models\TrackerAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $metrics = [
            'documents' => Document::count(),
            'billings' => Billing::count(),
            'pending' => Billing::whereIn('status', [
                Billing::STATUS_PENDING,
                Billing::STATUS_UNPAID,
                Billing::STATUS_OVERDUE,
            ])->count(),
        ];

        if ($user->isStaff()) {
            $metrics['assigned'] = TrackerAssignment::where('staff_id', $user->id)
                ->where('completed', false)
                ->count();
        }

        $activities = ActivityLog::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $user->loadMissing('teamMember');

        return view('admin.profile', [
            'user' => $user,
            'credentials' => $user->webauthnCredentials()->latest()->get(),
            'editMode' => $request->boolean('edit'),
            'metrics' => $metrics,
            'activities' => $activities,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $user = $request->user();

        if ($user->email !== $validated['email']) {
            $user->email_verified_at = null;
        }

        $user->fill($validated)->save();

        ActivityLog::record($user, 'admin.profile_updated', 'Updated own profile details.');

        return redirect()->route('admin.profile.index')->with('status', 'Profile updated.');
    }
}