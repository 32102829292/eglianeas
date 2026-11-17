<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\WorkPlanLegend;
use App\Models\User;
use App\Models\WorkPlanTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkPlanLegendController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->string('category')->toString();

        $legends = WorkPlanLegend::query()
            ->with('clients:id,name')
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $categories = WorkPlanLegend::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $taskCounts = WorkPlanTask::query()
            ->selectRaw('legend_code, COUNT(*) as total')
            ->whereNotNull('legend_code')
            ->groupBy('legend_code')
            ->pluck('total', 'legend_code');

        $clientUsers = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.work-plan.legends', [
            'legends' => $legends,
            'clients' => $clientUsers,
            'categories' => $categories,
            'selectedCategory' => $category,
            'taskCounts' => $taskCounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z][0-9]?$/', 'unique:work_plan_legends,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'usage_instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'requires_client' => ['sometimes', 'boolean'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer'],
        ]);

        $legend = WorkPlanLegend::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? ((int) WorkPlanLegend::max('sort_order') + 1),
            'usage_instructions' => $validated['usage_instructions'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'requires_client' => $request->boolean('requires_client'),
            'created_by' => auth()->id(),
        ]);

        $this->syncClients($legend, $request);

        ActivityLog::record(
            auth()->user(),
            'work_plan_legend.created',
            "Created activity legend {$legend->code} - {$legend->name}."
        );

        return back()->with('status', 'Activity legend created successfully.');
    }

    public function update(Request $request, WorkPlanLegend $legend): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z][0-9]?$/', 'unique:work_plan_legends,code,'.$legend->id],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'usage_instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'requires_client' => ['sometimes', 'boolean'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer'],
        ]);

        $legend->fill([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $legend->sort_order,
            'usage_instructions' => $validated['usage_instructions'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'requires_client' => $request->boolean('requires_client'),
            'edited_by' => auth()->id(),
        ])->save();

        $this->syncClients($legend, $request);

        ActivityLog::record(
            auth()->user(),
            'work_plan_legend.updated',
            "Updated activity legend {$legend->code} - {$legend->name}."
        );

        return back()->with('status', 'Activity legend updated successfully.');
    }

    /**
     * Save the clients picked for this code. Only applies while "Requires client name"
     * is on; switching it off keeps the saved list in case it is switched back on.
     */
    private function syncClients(WorkPlanLegend $legend, Request $request): void
    {
        if (! $legend->requires_client) {
            return;
        }

        $ids = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->whereIn('id', (array) $request->input('client_ids', []))
            ->pluck('id')
            ->all();

        $legend->clients()->sync($ids);
    }

    public function destroy(WorkPlanLegend $legend): RedirectResponse
    {
        $inUse = WorkPlanTask::query()->where('legend_code', $legend->code)->exists();

        if ($inUse) {
            $legend->update(['is_active' => false, 'edited_by' => auth()->id()]);

            return back()->with('status', 'Activity legend is in use and was deactivated instead of deleted.');
        }

        $legend->delete();

        ActivityLog::record(
            auth()->user(),
            'work_plan_legend.deleted',
            "Deleted activity legend {$legend->code} - {$legend->name}."
        );

        return back()->with('status', 'Activity legend deleted successfully.');
    }

    public function referenceBoard(): View
    {
        $legends = WorkPlanLegend::active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->groupBy(fn (WorkPlanLegend $legend) => $legend->category ?: 'Uncategorized');

        return view('work-plan.reference-board', [
            'groups' => $legends,
        ]);
    }
}