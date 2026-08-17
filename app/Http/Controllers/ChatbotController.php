<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $defaults = config('chatbot');

        $stored = Setting::get('chatbot_rules');

        if (! empty($stored)) {
            $decoded = json_decode((string) $stored, true);
            if (is_array($decoded)) {
                $defaults = array_replace_recursive($defaults, $decoded);
            }
        }

        return response()->json($defaults);
    }
}
