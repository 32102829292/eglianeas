<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\FeeRate;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with(['profile', 'billings'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->get();

        $clients = $clients
            ->map(fn (User $client): array => [
                'user' => $client,
                'billings' => $client->billings,
                'billing_count' => $client->billings->count(),
                'total_billed' => $client->billings->sum('total'),
                'total_paid' => $client->billings->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'status' => $this->clientStatus($client->billings),
            ])
            ->sortByDesc(fn (array $entry) => [$entry['billings']->count() > 0, $entry['status']])
            ->values();

        $year = Billing::currentYear();
        $quarter = Billing::currentQuarter();

        $missingSalesClients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->whereDoesntHave('billings', function ($query) use ($year, $quarter) {
                $query->where('year', $year)
                    ->where('quarter', $quarter)
                    ->whereNotNull('sales_submitted_at');
            })
            ->orderBy('name')
            ->get();

        return view('admin.billing.index', [
            'entries' => $clients,
            'q' => $q,
            'stats' => [
                'billed' => (float) Billing::query()->sum('total'),
                'collected' => (float) Billing::query()->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => (float) Billing::query()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'overdue' => Billing::query()->where('status', Billing::STATUS_OVERDUE)->count(),
            ],
            'missingSalesClients' => $missingSalesClients,
            'missingYear' => $year,
            'missingQuarter' => $quarter,
        ]);
    }

    public function show(User $client): View
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $billings = $client->billings()
            ->with('creator')
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->orderByDesc('id')
            ->get()
            ->groupBy('year')
            ->sortKeysDesc();

        return view('admin.billing.show', [
            'client' => $client,
            'billingsByYear' => $billings,
            'stats' => [
                'billed' => $client->billings()->sum('total'),
                'paid' => $client->billings()->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'count' => $client->billings()->count(),
            ],
        ]);
    }

    public function receipt(Billing $billing): View
    {
        return view('admin.billing.receipt', compact('billing'));
    }

    public function csv(Billing $billing): StreamedResponse
    {
        return $this->streamCsv($this->statementCsvRows($billing), $this->csvName($billing));
    }

    public function clientCsv(User $client): StreamedResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $rows = [];
        $rows[] = ['Business', $client->business_name ?: $client->name];
        $rows[] = ['Contact', $client->name.' <'.$client->email.'>'];
        $rows[] = [];
        $rows[] = ['Period', 'Due date', 'Sales', 'Tax 2551Q', 'Tax 1701Q', 'Cash-in', 'Fee 2551Q', 'Fee 1701Q', 'Fee Bookkeeping', 'Total', 'Status', 'Paid at'];
        foreach ($client->billings()->orderByDesc('year')->orderByDesc('quarter')->orderByDesc('id')->get() as $billing) {
            $rows[] = [
                $billing->periodTitle(),
                $billing->due_date?->format('Y-m-d'),
                $billing->sales,
                $billing->tax_2551q,
                $billing->tax_1701q,
                $billing->cash_in,
                $billing->fee_2551q,
                $billing->fee_1701q,
                $billing->fee_bookkeeping,
                $billing->total,
                $billing->statusLabel(),
                $billing->paid_at?->format('Y-m-d H:i'),
            ];
        }

        return $this->streamCsv($rows, Str::slug($client->business_name ?: $client->name).'-billing-'.now()->format('Y-m-d').'.csv');
    }

    public function create(): View
    {
        return view('admin.billing.create', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'rate' => (float) Setting::get('tax_2551q_rate', 3),
            'feeRates' => FeeRate::active()->ordered()->get(),
            'fees' => $this->feeDefaults(),
            'billing' => new Billing,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = Billing::STATUS_PENDING;

        $billing = new Billing($data);
        $billing->due_date = $this->resolvedDueDate($billing, $data['due_date'] ?? null);
        $billing->recomputeTotal();

        if ($request->boolean('sales_submitted') || (float) ($billing->sales ?? 0) > 0) {
            $billing->sales_submitted_at = now();
        }

        $billing->syncStatus();
        $billing->save();

        Setting::set('tax_2551q_rate', $billing->rate_2551q);

        $this->resolveSalesRequirement($billing);

        Notification::create([
            'user_id' => $billing->client_id,
            'title' => 'New billing statement',
            'body' => "A new billing statement for {$billing->periodTitle()} is available. Total payment: {$billing->money($billing->total)}.",
            'type' => 'billing',
            'link' => route('client.billing.show', $billing),
        ]);

        ActivityLog::record(auth()->user(), 'admin.billing_created', "Created {$billing->period_label} for {$billing->client?->name}.");

        return redirect()->route('admin.billing.index')->with('status', 'Billing record created.');
    }

    public function edit(Billing $billing): View
    {
        return view('admin.billing.edit', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'rate' => (float) Setting::get('tax_2551q_rate', 3),
            'feeRates' => FeeRate::active()->ordered()->get(),
            'fees' => $this->feeDefaults(),
            'billing' => $billing,
            'statuses' => Billing::STATUSES,
        ]);
    }

    public function update(Request $request, Billing $billing): RedirectResponse
    {
        $data = $this->validated($request);
        $data['updated_by'] = auth()->id();

        $billing->fill($data);
        $billing->due_date = $this->resolvedDueDate($billing, $data['due_date'] ?? null);
        $billing->recomputeTotal();

        if ($request->boolean('sales_submitted') && $billing->sales_submitted_at === null) {
            $billing->sales_submitted_at = now();
        }

        $billing->syncStatus();
        $billing->save();

        Setting::set('tax_2551q_rate', $billing->rate_2551q);

        $this->resolveSalesRequirement($billing);

        ActivityLog::record(auth()->user(), 'admin.billing_updated', "Updated {$billing->period_label} for {$billing->client?->name}.");

        return redirect()->route('admin.billing.show', $billing->client)->with('status', 'Billing record updated.');
    }

    public function pay(Request $request, Billing $billing): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Billing::STATUSES))],
            'paid_at' => ['nullable', 'date'],
        ]);

        $billing->status = $validated['status'];
        $billing->paid_at = $validated['status'] === Billing::STATUS_PAID
            ? (! empty($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : now())
            : null;
        $billing->updated_by = auth()->id();
        $billing->save();

        if ($billing->isPaid()) {
            Notification::create([
                'user_id' => $billing->client_id,
                'title' => 'Payment received',
                'body' => "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} has been marked as paid.",
                'type' => 'payment',
                'link' => route('client.collections.index'),
            ]);

            Notification::resolveGroup($billing->client_id, "billing_due:{$billing->id}");
        }

        ActivityLog::record(
            auth()->user(),
            'admin.billing_paid',
            "Marked {$billing->period_label} for {$billing->client?->name} as {$validated['status']}."
        );

        return back()->with('status', 'Billing status updated.');
    }

    public function destroy(Billing $billing): RedirectResponse
    {
        $label = $billing->period_label;
        $client = $billing->client;

        $billing->delete();

        ActivityLog::record(auth()->user(), 'admin.billing_deleted', "Deleted {$label}.");

        return redirect()->route('admin.billing.show', $client)->with('status', 'Billing record deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:users,id'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'period_label' => ['nullable', 'string', 'max:80'],
            'due_date' => ['nullable', 'date'],
            'sales' => ['nullable', 'numeric', 'min:0'],
            'rate_2551q' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_2551q' => ['nullable', 'numeric', 'min:0'],
            'tax_1701q' => ['nullable', 'numeric', 'min:0'],
            'cash_in' => ['nullable', 'numeric', 'min:0'],
            'fee_2551q' => ['nullable', 'numeric', 'min:0'],
            'fee_1701q' => ['nullable', 'numeric', 'min:0'],
            'fee_bookkeeping' => ['nullable', 'numeric', 'min:0'],
        ]);

        $quarter = (int) ($validated['quarter'] ?? 0);
        $year = (int) ($validated['year'] ?? (int) now()->format('Y'));

        $validated['quarter'] = $quarter ?: null;
        $validated['year'] = $year ?: null;

        if (empty(trim($validated['period_label'] ?? ''))) {
            $periodLabel = 'BILLING';
            if ($quarter >= 1 && $quarter <= 4) {
                $periodLabel = strtoupper(Billing::QUARTERS[$quarter]).' QUARTER '.$year.' BILLING';
            }
            $validated['period_label'] = $periodLabel;
        }

        return $validated;
    }

    private function resolvedDueDate(Billing $billing, ?string $dueDate): ?string
    {
        if (! empty($dueDate)) {
            return $dueDate;
        }

        if ($billing->quarter && $billing->year) {
            return Billing::defaultDueDate($billing->quarter, $billing->year)->toDateString();
        }

        return null;
    }

    private function feeDefaults(): array
    {
        return [
            'fee_2551q' => (string) Setting::get('fee_2551q', '0'),
            'fee_1701q' => (string) Setting::get('fee_1701q', '0'),
            'fee_bookkeeping' => (string) Setting::get('fee_bookkeeping', '0'),
        ];
    }

    private function resolveSalesRequirement(Billing $billing): void
    {
        if ($billing->year && $billing->quarter) {
            Notification::resolveGroup(
                $billing->client_id,
                "billing_missing_sales:{$billing->client_id}:{$billing->year}:{$billing->quarter}"
            );
        }
    }

    private function clientStatus(Collection $billings): string
    {
        if ($billings->where('status', Billing::STATUS_OVERDUE)->isNotEmpty()) {
            return Billing::STATUS_OVERDUE;
        }
        if ($billings->where('status', Billing::STATUS_UNPAID)->isNotEmpty()) {
            return Billing::STATUS_UNPAID;
        }
        if ($billings->where('status', Billing::STATUS_PENDING)->isNotEmpty()) {
            return Billing::STATUS_PENDING;
        }

        return $billings->isNotEmpty() ? Billing::STATUS_PAID : 'none';
    }

    private function statementCsvRows(Billing $billing): array
    {
        $client = $billing->client;

        return [
            ['Business', $client?->business_name ?: $client?->name],
            ['Contact', $client ? "{$client->name} <{$client->email}>" : ''],
            [],
            [strtoupper($billing->periodTitle())],
            [],
            ['SALES', number_format($billing->sales ?? 0, 2)],
            ['BIR REMITTANCES 2551Q', number_format($billing->tax_2551q ?? 0, 2)],
            ['BIR REMITTANCES 1701Q', number_format($billing->tax_1701q ?? 0, 2)],
            ['CASH IN', number_format($billing->cash_in ?? 0, 2)],
            ['RATE FEES 2551Q', number_format($billing->fee_2551q ?? 0, 2)],
            ['RATE FEES 1701Q', number_format($billing->fee_1701q ?? 0, 2)],
            ['RATE FEES BOOKKEEPING', number_format($billing->fee_bookkeeping ?? 0, 2)],
            [],
            ['TOTAL', number_format($billing->total ?? 0, 2)],
            ['STATUS', $billing->statusLabel()],
            ['DUE DATE', $billing->due_date?->format('F j, Y') ?? ''],
            ['PAID AT', $billing->paid_at?->format('F j, Y') ?? ''],
        ];
    }

    private function csvName(Billing $billing): string
    {
        $client = $billing->client;
    $name = Str::slug(($client?->business_name ?: $client?->name) ?: 'billing');

    return "{$name}-".Str::slug($billing->periodTitle()).'.csv';
    }

    private function streamCsv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function settings(): View
    {
        return view('admin.billing.settings', [
            'tax_2551q_rate' => Setting::get('tax_2551q_rate', '3'),
            'fee_2551q' => Setting::get('fee_2551q', '0'),
            'fee_1701q' => Setting::get('fee_1701q', '0'),
            'fee_bookkeeping' => Setting::get('fee_bookkeeping', '0'),
            'feeRates' => FeeRate::active()->ordered()->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tax_2551q_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'fee_2551q' => ['required', 'numeric', 'min:0'],
            'fee_1701q' => ['required', 'numeric', 'min:0'],
            'fee_bookkeeping' => ['required', 'numeric', 'min:0'],
        ]);

        Setting::set('tax_2551q_rate', $validated['tax_2551q_rate']);
        Setting::set('fee_2551q', $validated['fee_2551q']);
        Setting::set('fee_1701q', $validated['fee_1701q']);
        Setting::set('fee_bookkeeping', $validated['fee_bookkeeping']);

        ActivityLog::record(auth()->user(), 'billing.settings', 'Percentage tax rate (2551Q) and professional fee schedule updated.');

        return back()->with('status', 'Billing settings saved.');
    }

    public function storeFeeRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        FeeRate::query()->create([
            'amount' => $validated['amount'],
            'label' => $validated['label'] ?: null,
            'sort_order' => (int) FeeRate::query()->max('sort_order') + 1,
        ]);

        ActivityLog::record(auth()->user(), 'billing.fee_rate_added', "Added fee preset of {$validated['amount']}.");

        return back()->with('status', 'Fee preset added.');
    }

    public function destroyFeeRate(FeeRate $feeRate): RedirectResponse
    {
        if (FeeRate::query()->where('active', true)->count() <= 1) {
            return back()->withErrors(['fee_rates' => 'At least one fee preset is required.']);
        }

        $feeRate->delete();

        ActivityLog::record(auth()->user(), 'billing.fee_rate_removed', "Removed fee preset of {$feeRate->amount}.");

        return back()->with('status', 'Fee preset removed.');
    }

    public function availableYears(): JsonResponse
    {
        $years = Billing::whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return response()->json($years);
    }

    public function exportSummaryXlsx(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'year' => ['required', 'integer'],
        ]);

        $quarter = $validated['quarter'] ?? null;
        $year = (int) $validated['year'];
        $billings = $this->getFilteredBillings($quarter, $year);

        if ($billings->isEmpty()) {
            abort(404, 'No billing records found for this period.');
        }

        $this->logBillingExport('xlsx', $billings->count(), $quarter, $year);

        $periodLabel = $this->buildPeriodLabel($quarter, $year);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"Egliane-Billing-Summary-" . Str::slug($periodLabel) . '-' . now()->format('Y-m-d') . ".xlsx\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($billings, $periodLabel) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $colHeaders = [
                'Name', 'Sales', '2551Q (BIR)', '1701Q (BIR)', 'Cash In',
                'Rate', 'Fees', '2551Q — Amount', '1701Q — Amount',
                'Bookkeeping / Post-Closing Trial Balance', 'Amount (Total)',
            ];

            $colWidths = [24, 14, 14, 14, 14, 10, 14, 14, 14, 20, 14];

            foreach ($colHeaders as $col => $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                $cell = $sheet->getCell("{$colLetter}1");
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true);
                $sheet->getColumnDimension($colLetter)->setWidth($colWidths[$col]);
            }

            $row = 2;
            foreach ($billings as $billing) {
                $client = $billing->client;
                $rate = (float) $billing->rate_2551q;
                $fees = (float) $billing->fee_2551q + (float) $billing->fee_1701q;

                $values = [
                    $client?->business_name ?: $client?->name ?? '',
                    (float) $billing->sales,
                    (float) $billing->tax_2551q,
                    (float) $billing->tax_1701q,
                    (float) $billing->cash_in,
                    $rate > 0 ? $rate . '%' : '',
                    $fees,
                    (float) $billing->fee_2551q,
                    (float) $billing->fee_1701q,
                    (float) $billing->fee_bookkeeping,
                    (float) $billing->total,
                ];

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

    public function exportSummaryPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'year' => ['required', 'integer'],
        ]);

        $quarter = $validated['quarter'] ?? null;
        $year = (int) $validated['year'];
        $billings = $this->getFilteredBillings($quarter, $year);

        if ($billings->isEmpty()) {
            abort(404, 'No billing records found for this period.');
        }

        $this->logBillingExport('pdf', $billings->count(), $quarter, $year);

        $periodLabel = $this->buildPeriodLabel($quarter, $year);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.billing.summary-pdf', [
            'billings' => $billings,
            'periodLabel' => $periodLabel,
        ])->setPaper('a4', 'landscape');

        $filename = 'Egliane-Billing-Summary-' . Str::slug($periodLabel) . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    private function getFilteredBillings(?int $quarter, int $year): Collection
    {
        return Billing::with('client')
            ->where('year', $year)
            ->when($quarter, fn ($query) => $query->where('quarter', $quarter))
            ->orderBy('quarter')
            ->get()
            ->sortBy(fn (Billing $b) => strtolower($b->client?->business_name ?: $b->client?->name))
            ->values();
    }

    private function buildPeriodLabel(?int $quarter, int $year): string
    {
        if ($quarter) {
            return Billing::QUARTERS[$quarter] . ' Quarter ' . $year . ' Billing';
        }

        return 'All Quarters ' . $year . ' Billing';
    }

    private function logBillingExport(string $format, int $count, ?int $quarter, int $year): void
    {
        $period = $quarter ? 'Q' . $quarter . ' ' . $year : 'All Quarters ' . $year;

        ActivityLog::record(
            auth()->user(),
            'admin.billing_summary_exported',
            "Exported billing summary as {$format} ({$count} records, {$period})."
        );
    }
}
