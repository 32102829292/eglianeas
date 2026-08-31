<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientSurveyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveyController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->monthlySurveyDue()) {
            return redirect()->route('client.dashboard');
        }

        return view('client.survey');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'service_rating' => ['required', 'integer', 'between:1,5'],
            'portal_rating' => ['required', 'integer', 'between:1,5'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $user->monthlySurveyDue()) {
            return redirect()->route('client.dashboard');
        }

        ClientSurveyResponse::create([
            'user_id' => $user->id,
            'overall_rating' => (int) $validated['overall_rating'],
            'service_rating' => (int) $validated['service_rating'],
            'portal_rating' => (int) $validated['portal_rating'],
            'comments' => $validated['comments'] ?? null,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('client.dashboard')
            ->with('status', 'Thanks for your feedback!');
    }
}
