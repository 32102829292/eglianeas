<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveyController extends Controller
{
    public function index(Request $request): View
    {
        $responses = ClientSurveyResponse::query()
            ->with('user')
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $dueClients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->whereDoesntHave('surveyResponses', function ($query) {
                $query->where('submitted_at', '>=', now()->subDays(30));
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

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
