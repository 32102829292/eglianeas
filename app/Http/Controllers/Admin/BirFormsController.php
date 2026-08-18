<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BirFormStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BirFormsController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile', 'birFormStatuses')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('client_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('business_name')
            ->get()
            ->map(function (User $client): array {
                $statuses = $client->birFormStatuses->pluck('applicable', 'form_type');
                $applicableCount = $statuses->filter()->count();

                return [
                    'user' => $client,
                    'statuses' => $statuses,
                    'profile' => $client->profile,
                    'applicableCount' => $applicableCount,
                    'totalForms' => count(BirFormStatus::FORM_TYPES),
                ];
            });

        return view('admin.bir-forms.index', [
            'clients' => $clients,
            'q' => $q,
            'formTypes' => BirFormStatus::FORM_TYPES,
        ]);
    }

    public function toggleApplicable(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'form_type' => ['required', 'string', 'in:'.implode(',', BirFormStatus::FORM_TYPES)],
        ]);

        $record = BirFormStatus::firstOrCreate(
            ['client_id' => $client->id, 'form_type' => $validated['form_type']],
            ['status' => BirFormStatus::STATUS_NOT_FILED, 'applicable' => false]
        );

        $record->update([
            'applicable' => ! $record->applicable,
            'updated_by' => auth()->id(),
        ]);

        $state = $record->applicable ? 'applicable' : 'not applicable';
        $displayName = $client->business_name ?: $client->name;

        \App\Models\ActivityLog::record(
            auth()->user(),
            'admin.bir_form_toggled',
            "Marked {$validated['form_type']} as {$state} for {$displayName}."
        );

        return back()->with('status', "{$validated['form_type']} marked as {$state}.");
    }

    public function exportXlsx(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $q = trim((string) $request->get('q'));
        $entries = $this->getFilteredClients($q);

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

        return response()->stream(function () use ($entries) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $colHeaders = array_merge(
                ['Client ID', 'Client Name', 'Business Name', 'Business Type', 'Line of Business'],
                BirFormStatus::FORM_TYPES,
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
                $client = $entry['user'];
                $p = $entry['profile'];
                $statuses = $entry['statuses'];

                $values = [
                    $client->client_code ?? '',
                    $client->name,
                    $client->business_name ?? '',
                    $p?->business_type ?? '',
                    $p?->line_of_business ?? '',
                ];

                foreach (BirFormStatus::FORM_TYPES as $ft) {
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
        $entries = $this->getFilteredClients($q);

        if ($entries->isEmpty()) {
            abort(404, 'No clients found for the current filter.');
        }

        $this->logExport('pdf', $entries->count(), $q);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.bir-forms.summary-pdf', [
            'entries' => $entries,
            'formTypes' => BirFormStatus::FORM_TYPES,
        ])->setPaper('a4', 'landscape');

        $filename = 'Egliane-BIR-Forms-Summary-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    private function getFilteredClients(string $q): Collection
    {
        return User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile', 'birFormStatuses')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('client_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('business_name')
            ->get()
            ->map(function (User $client): array {
                $statuses = $client->birFormStatuses->pluck('applicable', 'form_type');
                $applicableCount = $statuses->filter()->count();

                return [
                    'user' => $client,
                    'statuses' => $statuses,
                    'profile' => $client->profile,
                    'applicableCount' => $applicableCount,
                ];
            });
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
