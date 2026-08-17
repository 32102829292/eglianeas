<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutContent;
use App\Models\ActivityLog;
use App\Models\CoreValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function edit(Request $request): View
    {
        $about = AboutContent::instance();
        $hasData = $about->mission !== null || CoreValue::count() > 0;
        $editing = $request->boolean('edit') || ! $hasData;

        return view('admin.about', [
            'about' => $about,
            'coreValues' => CoreValue::ordered()->get(),
            'editing' => $editing,
            'hasData' => $hasData,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mission' => ['required', 'string', 'max:2000'],
            'vision' => ['required', 'string', 'max:2000'],
            'values' => ['nullable', 'array'],
            'values.*' => ['required', 'string', 'max:100'],
        ]);

        AboutContent::updateOrCreate(
            ['id' => 1],
            ['mission' => $validated['mission'], 'vision' => $validated['vision']]
        );

        CoreValue::truncate();
        $values = array_filter($validated['values'] ?? [], fn ($v) => trim($v) !== '');
        foreach (array_values($values) as $i => $label) {
            CoreValue::create(['label' => trim($label), 'sort_order' => $i]);
        }

        ActivityLog::record(auth()->user(), 'settings.about_updated', 'Updated the About page content.');

        return back()->with('status', 'About page content saved.');
    }
}
