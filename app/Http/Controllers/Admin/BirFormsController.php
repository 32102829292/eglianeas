<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BirFormStatus;
use App\Models\ClientCompany;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BirFormsController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));
        // Deep-link support: billing create lands here with ?client_id=X so
        // the exact client's row is shown and highlighted for quick toggling.
        $highlightClientId = (int) $request->get('client_id') ?: null;
        $selectedCompanyId = (int) $request->get('client_company_id') ?: null;
        $formTypes = $this->formTypes();

        $clients = ClientCompany::query()
            ->with(['client.profile', 'birFormStatuses'])
            ->when($selectedCompanyId, fn ($query) => $query->whereKey($selectedCompanyId))
            ->when($highlightClientId, function ($query) use ($highlightClientId) {
                $query->where('client_id', $highlightClientId);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('company_name', 'like', "%{$q}%")
                        ->orWhere('company_code', 'like', "%{$q}%")
                        ->orWhereHas('client', function ($client) use ($q) {
                            $client->where('name', 'like', "%{$q}%")
                                ->orWhere('client_code', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy('company_name')
            ->paginate(50)
            ->withQueryString()
            ->through(function (ClientCompany $company) use ($formTypes): array {
                $statuses = $company->birFormStatuses->pluck('applicable', 'form_type');
                $applicableCount = $statuses->filter()->count();

                return [
                    'company' => $company,
                    'statuses' => $statuses,
                    'profile' => $company->client->profile,
                    'applicableCount' => $applicableCount,
                    'totalForms' => $formTypes->count(),
                ];
            });

        return view('admin.bir-forms.index', [
            'clients' => $clients,
            'q' => $q,
            'formTypes' => $formTypes,
            'highlightClientId' => $highlightClientId,
            'selectedCompanyId' => $selectedCompanyId,
            'companyOptions' => ClientCompany::query()->with('client:id,name')->orderBy('company_code')->get(),
        ]);
    }

    public function toggleApplicable(Request $request, User $client): RedirectResponse|JsonResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'form_type' => ['required', 'string', 'in:'.implode(',', $this->formTypes()->all())],
            'client_company_id' => ['required', 'exists:client_companies,id'],
        ]);

        $company = ClientCompany::whereKey($validated['client_company_id'])
            ->where('client_id', $client->id)
            ->firstOrFail();

        $record = BirFormStatus::firstOrCreate(
            ['client_id' => $client->id, 'client_company_id' => $company->id, 'form_type' => $validated['form_type']],
            ['status' => BirFormStatus::STATUS_NOT_FILED, 'applicable' => false]
        );

        $record->update([
            'applicable' => ! $record->applicable,
            'updated_by' => auth()->id(),
        ]);

        $state = $record->applicable ? 'applicable' : 'not applicable';
        $displayName = $company->company_name ?: $client->name;

        \App\Models\ActivityLog::record(
            auth()->user(),
            'admin.bir_form_toggled',
            "Marked {$validated['form_type']} as {$state} for {$displayName} ({$company->company_code})."
        );

        $message = "{$validated['form_type']} marked as {$state}.";

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'form_type' => $validated['form_type'],
                'applicable' => $record->applicable,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    public function exportXlsx(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $q = trim((string) $request->get('q'));
        $selectedCompanyId = (int) $request->get('client_company_id') ?: null;
        $entries = $this->getFilteredClients($q, $selectedCompanyId);
        $formTypes = $this->formTypes();

        if ($entries->isEmpty()) {
            abort(404, 'No clients found for the current filter.');
        }

        $this->logExport('xlsx', $entries->count(), $q);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"Egliane-BIR-Forms-Summary-" . now()->format('Y-m-d') . ".xlsx\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($entries, $formTypes) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $colHeaders = array_merge(
                ['Client ID', 'Client Name', 'Business Name', 'Business Type', 'Line of Business'],
                $formTypes->all(),
                ['Total']
            );

            $colWidths = [12, 20, 24, 18, 20, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8];

            foreach ($colHeaders as $col => $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                $cell = $sheet->getCell("{$colLetter}1");
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true);
                $sheet->getColumnDimension($colLetter)->setWidth($colWidths[$col]);
            }

            $row = 2;
            foreach ($entries as $entry) {
                $company = $entry['company'];
                $client = $company->client;
                $p = $entry['profile'];
                $statuses = $entry['statuses'];

                $values = [
                    $company->company_code ?? '',
                    $client->name,
                    $company->company_name ?? '',
                    $company->business_type ?? '',
                    $company->line_of_business ?? '',
                ];

                foreach ($formTypes as $ft) {
                    $values[] = ($statuses[$ft] ?? false) ? '✓' : '—';
                }

                $values[] = $entry['applicableCount'];

                foreach ($values as $col => $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                    $sheet->getCell("{$colLetter}{$row}")->setValue($value);
                }
                $row++;
            }

            $sheet->freezePane('A2');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setIncludeCharts(false);
            $writer->save('php://output');
        }, 200, $headers);
    }

    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $q = trim((string) $request->get('q'));
        $selectedCompanyId = (int) $request->get('client_company_id') ?: null;
        $entries = $this->getFilteredClients($q, $selectedCompanyId);
        $formTypes = $this->formTypes();

        if ($entries->isEmpty()) {
            abort(404, 'No clients found for the current filter.');
        }

        $this->logExport('pdf', $entries->count(), $q);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.bir-forms.summary-pdf', [
            'entries' => $entries,
            'formTypes' => $formTypes,
        ])->setPaper('a4', 'landscape');

        $filename = 'Egliane-BIR-Forms-Summary-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    private function getFilteredClients(string $q, ?int $selectedCompanyId = null): Collection
    {
        return ClientCompany::query()
            ->with(['client.profile', 'birFormStatuses'])
            ->when($selectedCompanyId, fn ($query) => $query->whereKey($selectedCompanyId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('company_name', 'like', "%{$q}%")
                        ->orWhere('company_code', 'like', "%{$q}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderBy('company_name')
            ->get()
            ->map(function (ClientCompany $company): array {
                $statuses = $company->birFormStatuses->pluck('applicable', 'form_type');
                $applicableCount = $statuses->filter()->count();

                return [
                    'company' => $company,
                    'statuses' => $statuses,
                    'profile' => $company->client->profile,
                    'applicableCount' => $applicableCount,
                ];
            });
    }

    /** @return Collection<int, string> */
    private function formTypes(): Collection
    {
        return collect(BirFormStatus::getFormTypeCodes())
            ->merge(BirFormStatus::query()->pluck('form_type'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function logExport(string $format, int $count, string $query): void
    {
        ActivityLog::record(
            auth()->user(),
            'admin.bir_forms_summary_exported',
            "Exported BIR Forms summary as {$format} ({$count} clients)."
        );
    }
}
