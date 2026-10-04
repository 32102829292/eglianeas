<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BirFormType;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BirFormTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can add BIR form types.');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:bir_form_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
        ]);

        $maxSort = BirFormType::max('sort_order') ?? 0;

        $formType = BirFormType::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? true,
            'sort_order' => $maxSort + 1,
        ]);

        ActivityLog::record(
            auth()->user(),
            'admin.bir_form_type_created',
            "Created BIR form type: {$formType->code} — {$formType->name}."
        );

        return redirect()->back()->with('status', "BIR form type \"{$formType->code} — {$formType->name}\" added successfully.");
    }
}