<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutContent;
use App\Models\ActivityLog;
use App\Models\CompanyCertificate;
use App\Models\CoreValue;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'certificates' => CompanyCertificate::ordered()->get(),
            'teamMembers' => TeamMember::ordered()->get(),
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

    public function uploadCertificate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('certificates', 'supabase');

        CompanyCertificate::create([
            'label' => $validated['label'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'sort_order' => CompanyCertificate::max('sort_order') + 1,
            'uploaded_at' => now(),
        ]);

        ActivityLog::record(auth()->user(), 'settings.certificate_uploaded', "Uploaded certificate: {$validated['label']}.");

        return back()->with('status', 'Certificate uploaded.');
    }

    public function destroyCertificate(CompanyCertificate $certificate): RedirectResponse
    {
        Storage::disk('supabase')->delete($certificate->file_path);
        $certificate->delete();

        ActivityLog::record(auth()->user(), 'settings.certificate_deleted', "Removed certificate: {$certificate->label}.");

        return back()->with('status', 'Certificate removed.');
    }
}
