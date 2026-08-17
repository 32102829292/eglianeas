<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Setting;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'announcements' => Announcement::query()
                ->with('poster')
                ->latest('posted_at')
                ->limit(50)
                ->get(),
            'chatbot' => $this->chatbotConfig(),
        ]);
    }

    public function chatbotConfig(): array
    {
        $defaults = config('chatbot');

        $stored = Setting::get('chatbot_rules');

        if (empty($stored)) {
            return $defaults;
        }

        $decoded = json_decode((string) $stored, true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $decoded);
    }
}
