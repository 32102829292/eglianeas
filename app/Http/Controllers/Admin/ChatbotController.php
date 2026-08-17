<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotController extends Controller
{
    public function edit(): View
    {
        return view('admin.chatbot', [
            'chatbot_enabled' => Setting::get('chatbot_enabled', '1'),
            'chatbot_rules' => Setting::get('chatbot_rules'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'chatbot_enabled' => ['sometimes', 'boolean'],
            'chatbot_welcome' => ['required', 'string', 'max:500'],
            'chatbot_fallback' => ['required', 'string', 'max:500'],
            'rules' => ['nullable', 'array'],
            'rules.*.keywords' => ['required', 'string'],
            'rules.*.response' => ['required', 'string'],
        ]);

        $rules = collect($validated['rules'] ?? [])
            ->filter(fn ($rule) => ! empty(trim($rule['keywords'])) && ! empty(trim($rule['response'])))
            ->map(fn ($rule) => [
                'keywords' => array_values(array_filter(array_map(fn ($k) => strtolower(trim($k)), explode(',', $rule['keywords'])))),
                'response' => trim($rule['response']),
            ])
            ->values()
            ->toArray();

        Setting::set('chatbot_enabled', $request->boolean('chatbot_enabled') ? '1' : '0');
        Setting::set('chatbot_rules', json_encode([
            'welcome_message' => $validated['chatbot_welcome'],
            'fallback_message' => $validated['chatbot_fallback'],
            'rules' => $rules,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        ActivityLog::record(auth()->user(), 'chatbot.update', 'Chatbot replies updated.');

        return back()->with('status', 'Chatbot configuration saved.');
    }
}
