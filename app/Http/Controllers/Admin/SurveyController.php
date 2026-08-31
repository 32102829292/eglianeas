<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use Illuminate\View\View;

class SurveyController extends Controller
{
    public function index(): View
    {
        $responses = ClientSurveyResponse::query()
            ->with('user')
            ->latest('submitted_at')
            ->latest('id')
            ->limit(200)
            ->get();

        $dueClients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->whereDoesntHave('surveyResponses', function ($query) {
                $query->where('submitted_at', '>=', now()->subDays(30));
            })
            ->orderBy('name')
            ->get();

        $average = ClientSurveyResponse::query()
            ->where('submitted_at', '>=', now()->subDays(30))
            ->average('overall_rating');

        return view('admin.surveys.index', [
            'responses' => $responses,
            'dueClients' => $dueClients,
            'average' => $average === null ? null : round((float) $average, 2),
        ]);
    }
}
