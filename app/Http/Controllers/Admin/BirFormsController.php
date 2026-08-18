<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BirFormStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BirFormsController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('client_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('business_name')
            ->get()
            ->map(function (User $client): array {
                $statuses = $client->birFormStatuses()->pluck('applicable', 'form_type');
                $applicableCount = $statuses->filter()->count();

                return [
                    'user' => $client,
                    'profile' => $client->profile,
                    'applicableCount' => $applicableCount,
                    'totalForms' => count(BirFormStatus::FORM_TYPES),
                ];
            });

        return view('admin.bir-forms.index', [
            'clients' => $clients,
            'q' => $q,
            'formTypes' => BirFormStatus::FORM_TYPES,
        ]);
    }

    public function toggleApplicable(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'form_type' => ['required', 'string', 'in:'.implode(',', BirFormStatus::FORM_TYPES)],
        ]);

        $record = BirFormStatus::firstOrCreate(
            ['client_id' => $client->id, 'form_type' => $validated['form_type']],
            ['status' => BirFormStatus::STATUS_NOT_FILED, 'applicable' => false]
        );

        $record->update([
            'applicable' => ! $record->applicable,
            'updated_by' => auth()->id(),
        ]);

        $state = $record->applicable ? 'applicable' : 'not applicable';
        $displayName = $client->business_name ?: $client->name;

        \App\Models\ActivityLog::record(
            auth()->user(),
            'admin.bir_form_toggled',
            "Marked {$validated['form_type']} as {$state} for {$displayName}."
        );

        return back()->with('status', "{$validated['form_type']} marked as {$state}.");
    }
}
