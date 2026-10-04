<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BillingStatementMail;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\ClientCompany;
use App\Models\BirFormStatus;
use App\Models\FeeRate;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Support\BillingSummaryMatrix;
use App\Support\Quarter;
use App\Support\SupportedBanks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    // Vertical padding (top + bottom) of a receipt cell in the batch sheet.
    public const CELL_VERTICAL_PAD_MM = 3.0;

    // Calibrated safety multiplier: rendered pairs measure ~1.06x the estimate,
    // so budgeting the page with this factor prevents a page spill.
    public const PAIR_HEIGHT_SAFETY = 1.06;

    public function index(Request $request): View|RedirectResponse
    {
        $q = trim((string) $request->get('q'));
        $activeQuarter = Quarter::fromKey((string) $request->get('quarter'));
        $amountOrder = $request->get('amount_order');
        $amountOrder = in_array($amountOrder, ['asc', 'desc'], true) ? $amountOrder : null;

        // Native GET forms serialize every non-disabled control, so an empty
        // search field produces "?q=" even when nothing was searched. The
        // frontend disables empty fields on submit, but that only runs when
        // the submit event fires. Canonicalize on the server instead so the
        // URL stays clean regardless of how the request was made.
        if ($redirect = $this->canonicalBillingQuery($request, $q, $activeQuarter)) {
            return $redirect;
        }

        // Priority ordering reproduced from clientStatus(): clients with
        // overdue/unpaid/pending statements surface first, then paid-only,
        // then those with no statements. Kept in SQL so pagination is stable.
        // When a quarter is selected, priorities are computed only within it.
        $priority = '(SELECT MAX(CASE b.status '
            ."WHEN '".Billing::STATUS_OVERDUE."' THEN 5 "
            ."WHEN '".Billing::STATUS_UNPAID."' THEN 4 "
            ."WHEN '".Billing::STATUS_PENDING."' THEN 3 "
            ."WHEN '".Billing::STATUS_PAID."' THEN 2 "
            .'ELSE 1 END) FROM billings b WHERE b.client_id = users.id';

        if ($activeQuarter) {
            $priority .= ' AND b.year = '.$activeQuarter->year.' AND b.quarter = '.$activeQuarter->quarter;
        }

        $priority .= ')';

        $amountTotal = '(SELECT COALESCE(SUM(b_amount.total), 0) FROM billings b_amount WHERE b_amount.client_id = users.id'
            ." AND b_amount.status IN ('".Billing::STATUS_PENDING."', '".Billing::STATUS_UNPAID."', '".Billing::STATUS_OVERDUE."', '".Billing::STATUS_PAID."')";
        if ($activeQuarter) {
            $amountTotal .= ' AND b_amount.year = '.$activeQuarter->year.' AND b_amount.quarter = '.$activeQuarter->quarter;
        }
        $amountTotal .= ')';

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with(['profile', 'billings' => function ($query) use ($activeQuarter) {
                if ($activeQuarter) {
                    $query->where('year', $activeQuarter->year)
                        ->where('quarter', $activeQuarter->quarter);
                }
            }])
            ->withCount(['birFormStatuses as applicable_forms_count' => function ($query) {
                $query->where('applicable', true);
            }])
            // Loads each client's BIR form codes, statuses and names for the
            // summary column. Display only: no filtering, totals or writes.
            ->with('birFormStatuses.formType')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($activeQuarter, function ($query) use ($activeQuarter) {
                $query->whereHas('billings', function ($query) use ($activeQuarter) {
                    $query->where('year', $activeQuarter->year)
                        ->where('quarter', $activeQuarter->quarter);
                });
            })
            ->when($amountOrder, fn ($query, $direction) => $query->orderByRaw("{$amountTotal} {$direction} NULLS LAST"), fn ($query) => $query->orderByRaw("{$priority} DESC NULLS LAST"))
            ->orderByRaw("COALESCE(NULLIF(business_name, ''), name) asc")
            ->paginate(50)
            ->withQueryString()
            ->through(fn (User $client): array => [
                'user' => $client,
                'billings' => $client->billings,
                'billing_count' => $client->billings->whereIn('status', Billing::ACTIVE_STATUSES)->count(),
                'total_billed' => $client->billings->whereIn('status', Billing::ACTIVE_STATUSES)->sum('total'),
                'total_paid' => $client->billings->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'status' => $this->clientStatus($client->billings),
                'bir_ready' => ((int) $client->applicable_forms_count) > 0,
            ]);

        [$downloadYears, $defaultDownloadYear] = $this->downloadYearOptions($activeQuarter);

        return view('admin.billing.index', [
            'entries' => $clients,
            'q' => $q,
            'activeQuarter' => $activeQuarter,
            'amountOrder' => $amountOrder,
            'availableQuarters' => Billing::filterQuarters(),
            'stats' => $this->summaryStats($activeQuarter),
            'downloadYears' => $downloadYears,
            'defaultDownloadYear' => $defaultDownloadYear,
        ]);
    }

    /**
     * Summary-card figures. When a summary period is active they are scoped to
     * that exact quarter/year (same definition the client table below uses), so
     * the cards always reflect the selected period; with no period selected they
     * span the full ledger. Old behavior ignored the quarter entirely.
     */
    private function summaryStats(?Quarter $activeQuarter): array
    {
        $base = Billing::query();
        if ($activeQuarter) {
            $base->where('year', $activeQuarter->year)
                ->where('quarter', $activeQuarter->quarter);
        }

        return [
            'billed' => (float) (clone $base)->whereIn('status', Billing::ACTIVE_STATUSES)->sum('total'),
            'collected' => (float) (clone $base)->where('status', Billing::STATUS_PAID)->sum('total'),
            'outstanding' => (float) (clone $base)->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
            'overdue' => (clone $base)->where('status', Billing::STATUS_OVERDUE)->count(),
        ];
    }

    /**
     * Years offered in the "Download Billing Summary" panel, newest first, and
     * the default year preselected from the active summary period (otherwise the
     * current year, falling back to the newest available year).
     *
     * @return array{0: array<int, int>, 1: int}
     */
    private function downloadYearOptions(?Quarter $activeQuarter): array
    {
        $years = Billing::query()
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->push((int) now()->format('Y'))
            ->unique()
            ->sortByDesc(fn (int $year): int => $year)
            ->values()
            ->all();

        $default = $activeQuarter?->year
            ?? (in_array((int) now()->format('Y'), $years, true) ? (int) now()->format('Y') : ($years[0] ?? (int) now()->format('Y')));

        return [$years, $default];
    }

    /**
     * When a GET filter request carries an empty "q" or "quarter" parameter
     * (e.g. a bare search form submission), redirect to the clean equivalent
     * URL so bookmarks/shared links never retain "?q=&quarter=" cruft. Invalid
     * quarter keys are left untouched — they already fall back to "all".
     */
    private function canonicalBillingQuery(Request $request, string $q, ?Quarter $activeQuarter): ?RedirectResponse
    {
        $query = $request->query();

        // Unexpected extra parameters: leave the URL alone to avoid data loss.
        if (count(array_diff_key($query, array_flip(['q', 'quarter', 'page']))) > 0) {
            return null;
        }

        // Empty query params arrive as '' before the request goes through the
        // ConvertEmptyStringsToNull middleware, then become null afterward —
        // treat both as empty.
        $emptyFilterKey = collect($query)
            ->filter(fn ($value) => $value === null || (is_string($value) && trim($value) === ''))
            ->keys()
            ->intersect(['q', 'quarter'])
            ->isNotEmpty();

        if (! $emptyFilterKey) {
            return null;
        }

        $params = [];
        if ($q !== '') {
            $params['q'] = $q;
        }
        if ($activeQuarter) {
            $params['quarter'] = $activeQuarter->key();
        }
        if (($page = $query['page'] ?? null) !== null && trim((string) $page) !== '') {
            $params['page'] = $page;
        }

        return redirect()->route('admin.billing.index', $params ?: null);
    }

    public function show(Request $request, User $client): View
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        // The branch schema is deliberately deployed in a later migration.
        // Keep the existing parent-client statement available until then; once
        // the table exists, this uses the real company/branch records only.
        $companies = Schema::hasTable('client_companies') ? $client->companies()->get() : collect();
        $selectedCompany = $companies->firstWhere('id', $request->integer('client_company_id'))
            ?? $companies->first();

        $billings = $client->billings()
            ->with(['creator', 'lineItems'])
            ->when($selectedCompany, fn ($query) => $query->where('client_company_id', $selectedCompany->id))
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->orderByDesc('id')
            ->get()
            ->groupBy('year')
            ->sortKeysDesc();

        return view('admin.billing.show', [
            'client' => $client,
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'billingsByYear' => $billings,
            'stats' => [
                'billed' => $client->billings()->when($selectedCompany, fn ($query) => $query->where('client_company_id', $selectedCompany->id))->whereIn('status', Billing::ACTIVE_STATUSES)->sum('total'),
                'paid' => $client->billings()->when($selectedCompany, fn ($query) => $query->where('client_company_id', $selectedCompany->id))->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings()->when($selectedCompany, fn ($query) => $query->where('client_company_id', $selectedCompany->id))->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'count' => $client->billings()->when($selectedCompany, fn ($query) => $query->where('client_company_id', $selectedCompany->id))->whereIn('status', Billing::ACTIVE_STATUSES)->count(),
            ],
        ]);
    }

    public function receipt(Billing $billing): View
    {
        $billing->load('lineItems');

        return view('admin.billing.receipt', compact('billing'));
    }

    public function sendEmail(Request $request, Billing $billing): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $client = $billing->client;
        abort_unless($client, 404, 'This billing has no client.');

        $recipient = $validated['email'] ?? $client->email;

        if (! $recipient) {
            return back()->with('error', 'This client has no registered email address.');
        }

        if (config('mail.default') === 'brevo' && ! config('services.brevo.key')) {
            return back()->with('error', 'Brevo is not configured yet (missing BREVO_API_KEY). The statement was not sent.');
        }

        try {
            Mail::to($recipient)->send(new BillingStatementMail($billing->loadMissing(['client.profile', 'lineItems'])));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The email could not be sent: '.$e->getMessage());
        }

        $label = $billing->period_label;
        ActivityLog::record(
            auth()->user(),
            'admin.billing_emailed',
            "Emailed the {$label} billing statement to {$recipient}."
        );

        return back()->with('status', "Billing statement sent to {$recipient}.");
    }

    public function csv(Billing $billing): StreamedResponse
    {
        $billing->load('lineItems');

        return $this->streamCsv($this->statementCsvRows($billing), $this->csvName($billing));
    }

    public function clientCsv(User $client): StreamedResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $billings = $client->billings()
            ->with('lineItems')
            ->whereIn('status', Billing::ACTIVE_STATUSES)
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->orderByDesc('id')
            ->get();

        $rows = [];
        $rows[] = ['Client', 'Billing Period', 'Cash In', 'Total', 'Status', 'Due Date', 'Paid At'];
        foreach ($billings as $billing) {
            $rows[] = [
                $client->business_name ?: $client->name,
                $billing->periodTitle(),
                $this->csvMoney(
                    $billing->lineItems
                        ->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
                        ->whereNull('form_type')
                        ->sum('amount')
                ),
                $this->csvMoney($billing->total),
                $billing->statusLabel(),
                $this->csvDate($billing->due_date),
                $this->csvDate($billing->paid_at),
            ];
        }

        return $this->streamCsv($rows, Str::slug($client->business_name ?: $client->name).'-billing-'.now()->format('Y-m-d').'.csv');
    }

    public function create(Request $request): View
    {
        // Pre-select a client when arriving from that client's billing page.
        $selectedClientId = null;
        $selectedCompanyId = null;
        $requestedClient = $request->query('client') ?: $request->query('client_id');
        if ($requestedClient) {
            $selectedClientId = (int) $requestedClient;
            $exists = User::where('id', $selectedClientId)->where('role', User::ROLE_CLIENT)->exists();
            if (! $exists) {
                $selectedClientId = null;
            }
        }

        // Default the quarter for the selected client's current year
        // (Q1 if none, otherwise the first unbilled quarter in sequence).
        $defaultQuarter = null;
        if ($selectedClientId) {
            $requestedCompanyId = (int) $request->query('client_company_id');
            if ($requestedCompanyId && ClientCompany::whereKey($requestedCompanyId)->where('client_id', $selectedClientId)->exists()) {
                $selectedCompanyId = $requestedCompanyId;
            }
            $year = (int) now()->format('Y');
            $quarter = Billing::nextQuarterFor($selectedClientId, $year);
            if ($quarter > 0) {
                $defaultQuarter = $quarter;
            }
        }

        return view('admin.billing.create', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->with('companies')->orderBy('name')->get(),
            'feeRates' => FeeRate::active()->ordered()->get(),
            'billing' => new Billing,
            'selectedClientId' => $selectedClientId,
            'selectedCompanyId' => $selectedCompanyId,
            'defaultQuarter' => $defaultQuarter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, create: true);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = Billing::STATUS_UNPAID;

        $quarter = $data['quarter'] ?? null;
        $year = $data['year'] ?? null;

        if ($quarter !== null && $year !== null) {
            $duplicate = Billing::where('client_id', $data['client_id'])
                ->when($data['client_company_id'] ?? null, fn ($query, $companyId) => $query->where('client_company_id', $companyId))
                ->where('quarter', $quarter)
                ->where('year', $year)
                ->exists();

            if ($duplicate) {
                $periodName = Billing::QUARTERS[$quarter].' Quarter '.$year;

                return back()->withInput()->withErrors([
                    'client_id' => "A billing statement for {$periodName} already exists for this client. Please edit the existing statement instead.",
                ]);
            }
        }

        // Feasibility gate: a statement can only be created once the client has
        // at least one applicable BIR form selected on the BIR Forms page.
        $applicableForms = BirFormStatus::where('client_id', $data['client_id'])
            ->when($data['client_company_id'] ?? null, fn ($query, $companyId) => $query->where('client_company_id', $companyId))
            ->where('applicable', true)
            ->count();

        if ($applicableForms === 0) {
            return back()->withInput()->withErrors([
                'client_id' => 'This client has no applicable BIR forms. Add at least one BIR form before creating a billing statement.',
            ]);
        }

        // A billing statement must have at least one line item with an amount.
        $hasAmountItems = collect($request->input('line_items', []))->contains(
            fn ($item) => (float) ($item['amount'] ?? 0) > 0
        );

        if (! $hasAmountItems) {
            return back()->withInput()->withErrors([
                'line_items' => 'Add at least one line item with an amount greater than zero.',
            ]);
        }

        $billing = DB::transaction(function () use ($data, $request) {
            $billing = new Billing($data);
            $billing->due_date = $this->resolvedDueDate($billing, $data['due_date'] ?? null);

            $billing->save();

            $this->syncLineItems($billing, $request);
            $billing->recomputeTotal();
            $billing->save();

            return $billing;
        });

        Notification::create([
            'user_id' => $billing->client_id,
            'title' => 'New billing statement',
            'body' => "A new billing statement for {$billing->periodTitle()} is available. Total payment: {$billing->money($billing->total)}.",
            'type' => 'billing',
            'link' => route('client.billing.show', $billing),
        ]);

        $client = User::find($billing->client_id);
        if ($client) {
            PushNotificationService::send($client, 'New billing statement', "A new billing for {$billing->periodTitle()} is available. Total: {$billing->money($billing->total)}.", route('client.billing.show', $billing));
        }

        ActivityLog::record(auth()->user(), 'admin.billing_created', "Created {$billing->period_label} for {$billing->client?->name}.");

        return redirect()->route('admin.billing.index')->with('status', 'Billing record created.');
    }

    public function edit(Billing $billing): View
    {
        $billing->load('lineItems');

        return view('admin.billing.edit', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->with('companies')->orderBy('name')->get(),
            'feeRates' => FeeRate::active()->ordered()->get(),
            'billing' => $billing,
            'statuses' => Billing::STATUSES,
        ]);
    }

    public function update(Request $request, Billing $billing): RedirectResponse
    {
        abort_if($billing->isPaid(), 403, 'Paid billings cannot be edited.');

        // Capture the state before the edit so we can log exactly what changed
        // for finalized (non-draft) billings, whose contents may no longer match
        // what the client has already seen.
        $wasFinalized = ! $billing->isDraft();
        $before = $wasFinalized ? $this->editableSnapshot($billing) : null;

        $data = $this->validated($request);
        $data['updated_by'] = auth()->id();

        $billing = DB::transaction(function () use ($request, $billing, $data) {
            $billing->fill($data);
            $billing->due_date = $this->resolvedDueDate($billing, $data['due_date'] ?? null);
            $billing->save();

            $this->syncLineItems($billing, $request);
            $billing->recomputeTotal();
            $billing->save();

            return $billing;
        });

        ActivityLog::record(auth()->user(), 'admin.billing_updated', "Updated {$billing->period_label} for {$billing->client?->name}.");

        if ($wasFinalized) {
            $after = $this->editableSnapshot($billing);
            $changed = [];

            foreach (array_keys($before) as $field) {
                $old = (string) ($before[$field] ?? '');
                $new = (string) ($after[$field] ?? '');
                if ($old !== $new) {
                    $changed[] = "{$field}: {$old} → {$new}";
                }
            }

            ActivityLog::record(
                auth()->user(),
                'admin.billing_edited_after_finalize',
                "Edited finalized billing {$billing->period_label} for {$billing->client?->name} — changed: "
                .($changed ? implode('; ', $changed) : 'no field changes recorded')
                .'.'
            );
        }

        return redirect()->route('admin.billing.show', $billing->client)->with('status', 'Billing record updated.');
    }

    /**
     * A normalized snapshot of the billing's editable contents, used to diff
     * what changed when an already-finished billing is edited after finalizing.
     */
    private function editableSnapshot(Billing $billing): array
    {
        $client = $billing->client;

        return [
            'client' => $client ? ($client->business_name ?: $client->name) : '',
            'quarter' => (string) ($billing->quarter ?? ''),
            'year' => (string) ($billing->year ?? ''),
            'due_date' => $billing->due_date?->toDateString() ?? '',
            'cash_in' => (string) (float) $billing->cash_in,
            'total' => (string) (float) $billing->total,
            'line_items' => $billing->lineItems()
                ->orderBy('id')
                ->get()
                ->map(fn ($item) => trim($item->label).': '.(float) $item->amount)
                ->implode(' | '),
        ];
    }

    public function finalize(Billing $billing): RedirectResponse
    {
        abort_unless($billing->isDraft(), 422, 'Only draft billings can be finalized.');

        $billing->status = Billing::STATUS_UNPAID;
        $billing->updated_by = auth()->id();
        $billing->save();

        ActivityLog::record(
            auth()->user(),
            'admin.billing_finalized',
            "Finalized the {$billing->periodTitle()} draft for {$billing->client?->name}."
        );

        return back()->with('status', 'Draft billing finalized and made active.');
    }

    public function pay(Request $request, Billing $billing): RedirectResponse
    {
        abort_if(auth()->user()->isStaff(), 403, 'Staff cannot mark a billing as paid.');

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Billing::ACTIVE_STATUSES)],
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

            $client = User::find($billing->client_id);
            if ($client) {
                PushNotificationService::send($client, 'Payment received', "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} has been marked as paid.", route('client.collections.index'));
            }

            Notification::resolveGroup($billing->client_id, "billing_due:{$billing->id}");

            // Auto-prepare the NEXT quarter as a draft (template from this paid
            // billing) so the admin can review and finalize it later. No draft
            // is created at Q4 (next cycle starts fresh the following year).
            if ($draft = Billing::makeNextDraft($billing)) {
                ActivityLog::record(
                    auth()->user(),
                    'admin.billing_draft_created',
                    "Prepared a draft {$draft->periodTitle()} for {$draft->client?->name} based on the paid {$billing->periodTitle()}."
                );
            }
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
        abort_if($billing->isPaid(), 403, 'Paid billings cannot be deleted.');

        $label = $billing->period_label;
        $client = $billing->client;

        $billing->delete();

        ActivityLog::record(auth()->user(), 'admin.billing_deleted', "Deleted {$label}.");

        return redirect()->route('admin.billing.show', $client)->with('status', 'Billing record deleted.');
    }

    public function applicableForms(Request $request): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:users,id', 'client_company_id' => 'nullable|exists:client_companies,id']);

        $this->assertCompanyBelongsToClient($request->integer('client_company_id'), $request->integer('client_id'));

        $forms = BirFormStatus::where('client_id', $request->client_id)
            ->when($request->client_company_id, fn ($query, $companyId) => $query->where('client_company_id', $companyId))
            ->where('applicable', true)
            ->pluck('form_type')
            ->values();

        return response()->json(['forms' => $forms]);
    }

    public function lastBilling(Request $request): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:users,id', 'client_company_id' => 'nullable|exists:client_companies,id']);

        $this->assertCompanyBelongsToClient($request->integer('client_company_id'), $request->integer('client_id'));

        $lastBilling = Billing::with('lineItems')
            ->where('client_id', $request->client_id)
            ->when($request->client_company_id, fn ($query, $companyId) => $query->where('client_company_id', $companyId))
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->orderByDesc('id')
            ->first();

        if (! $lastBilling) {
            return response()->json(['line_items' => []]);
        }

        $lineItems = $lastBilling->lineItems->map(fn (BillingLineItem $item) => [
            'category' => $item->category,
            'form_type' => $item->form_type,
            'label' => $item->label,
            'month' => $item->month,
            'amount' => $item->amount,
            'fee_rate_id' => $item->fee_rate_id,
        ])->values()->all();

        return response()->json([
            'period_title' => $lastBilling->periodTitle(),
            'line_items' => $lineItems,
        ]);
    }

    private function validated(Request $request, bool $create = false): array
    {
        $rules = [
            'client_id' => ['required', 'exists:users,id'],
            'client_company_id' => ['nullable', 'exists:client_companies,id'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'period_label' => ['nullable', 'string', 'max:80'],
            'due_date' => ['nullable', 'date'],
            'cash_in' => ['nullable', 'numeric', 'min:0'],
        ];

        // A new billing statement must always identify its period before it can
        // be saved. Quarter and year are left lenient on edits (legacy draft
        // records may lack them) but are required the first time a statement
        // is created.
        if ($create) {
            $rules['quarter'] = ['required', 'integer', 'between:1,4'];
            $rules['year'] = ['required', 'integer', 'between:2000,2100'];
        }

        $validated = $request->validate($rules, [], [
            'client_id' => 'client',
            'quarter' => 'billing quarter',
            'year' => 'billing year',
            'period_label' => 'billing period label',
            'due_date' => 'due date',
            'cash_in' => 'cash-in amount',
        ]);

        $this->assertCompanyBelongsToClient($validated['client_company_id'] ?? null, (int) $validated['client_id']);

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

    private function assertCompanyBelongsToClient(?int $companyId, int $clientId): void
    {
        if ($companyId && ! ClientCompany::whereKey($companyId)->where('client_id', $clientId)->exists()) {
            abort(422, 'The selected company does not belong to this client.');
        }
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

    private function syncLineItems(Billing $billing, Request $request): void
    {
        $items = $request->input('line_items', []);

        // Delete existing line items and recreate
        $billing->lineItems()->delete();

        foreach ($items as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            if ($amount <= 0 && empty($item['is_cash_in'])) {
                continue;
            }

            $category = $item['category'] ?? BillingLineItem::CATEGORY_BIR_REMITTANCE;
            $formType = $item['form_type'] ?? null;
            $month = ! empty($item['month']) ? (int) $item['month'] : null;
            $label = $item['label'] ?? '';
            $feeRateId = ! empty($item['fee_rate_id']) ? (int) $item['fee_rate_id'] : null;

            // Build label if empty
            if (empty($label)) {
                $label = $this->buildLineItemLabel($category, $formType, $month);
            }

            BillingLineItem::create([
                'billing_id' => $billing->id,
                'category' => $category,
                'form_type' => $formType,
                'label' => $label,
                'month' => $month,
                'amount' => $amount,
                'fee_rate_id' => $feeRateId,
            ]);
        }
    }

    private function buildLineItemLabel(string $category, ?string $formType, ?int $month): string
    {
        $monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];

        return match ($category) {
            BillingLineItem::CATEGORY_BIR_REMITTANCE => $formType ? "{$formType} Remittance" : 'Cash In',
            BillingLineItem::CATEGORY_PROFESSIONAL_FEE => $formType
                ? "Professional Fee — {$formType}".($month ? " ({$monthNames[$month]})" : '')
                : 'Professional Fee',
            BillingLineItem::CATEGORY_BOOKKEEPING_FEE => 'Bookkeeping',
            BillingLineItem::CATEGORY_POST_CLOSING_TB => 'Post-Closing Trial Balance',
            BillingLineItem::CATEGORY_INVENTORY_LIST => 'Inventory List (Notarized)',
            BillingLineItem::CATEGORY_OTHER_ATTACHMENT => 'Other Attachment',
            BillingLineItem::CATEGORY_DATA_ENTRY => 'Data Entry',
            default => $formType ?? 'Line Item',
        };
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
        $items = $billing->lineItems;

        // One column per BIR form type actually present in this statement
        // (e.g. "1701 Remittance", "2550Q Remittance"), each summed across the
        // statement's filing months. Cash In is its own item (BIR remittance
        // lines with no form type), matching the summary XLSX/PDF exports.
        $formTypes = $items
            ->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
            ->whereNotNull('form_type')
            ->pluck('form_type')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $categories = [
            BillingLineItem::CATEGORY_PROFESSIONAL_FEE => 'Professional Fee',
            BillingLineItem::CATEGORY_BOOKKEEPING_FEE => 'Bookkeeping Fee',
            BillingLineItem::CATEGORY_POST_CLOSING_TB => 'Post-Closing Trial Balance',
            BillingLineItem::CATEGORY_INVENTORY_LIST => 'Inventory List (Notarized)',
            BillingLineItem::CATEGORY_OTHER_ATTACHMENT => 'Other Attachment',
            BillingLineItem::CATEGORY_DATA_ENTRY => 'Data Entry',
        ];

        $statuses = [
            BillingLineItem::CATEGORY_BIR_REMITTANCE,
            BillingLineItem::CATEGORY_PROFESSIONAL_FEE,
            BillingLineItem::CATEGORY_BOOKKEEPING_FEE,
            BillingLineItem::CATEGORY_POST_CLOSING_TB,
            BillingLineItem::CATEGORY_INVENTORY_LIST,
            BillingLineItem::CATEGORY_OTHER_ATTACHMENT,
            BillingLineItem::CATEGORY_DATA_ENTRY,
        ];

        $headers = ['Client', 'Billing Period'];
        $values = [$client?->business_name ?: $client?->name, $billing->periodTitle()];

        foreach ($formTypes as $formType) {
            $headers[] = "{$formType} Remittance";
            $values[] = $this->csvMoney(
                $items->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
                    ->where('form_type', $formType)
                    ->sum('amount')
            );
        }

        $values[] = $this->csvMoney(
            $items->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
                ->whereNull('form_type')
                ->sum('amount')
        );
        $headers[] = 'Cash In';

        foreach ($categories as $category => $label) {
            $headers[] = $label;
            $values[] = $this->csvMoney($items->where('category', $category)->sum('amount'));
        }

        // Free-text "custom" items don't belong to a named category; surface
        // them as a single column so the Total still reconciles.
        $customItems = $items->whereNotIn('category', $statuses);
        if ($customItems->isNotEmpty()) {
            $headers[] = 'Custom Items';
            $values[] = $this->csvMoney($customItems->sum('amount'));
        }

        $headers[] = 'Total';
        $values[] = $this->csvMoney($billing->total);

        $headers[] = 'Status';
        $values[] = $billing->statusLabel();

        $headers[] = 'Due Date';
        $values[] = $this->csvDate($billing->due_date);

        $headers[] = 'Paid At';
        $values[] = $this->csvDate($billing->paid_at);

        return [$headers, $values];
    }

    /**
     * Money as a bare decimal ("1500.00") so Excel treats it as a number, not
     * a formatted string. Empty for genuinely absent values.
     */
    private function csvMoney(mixed $value): string
    {
        $amount = (float) $value;

        return $amount > 0 ? number_format($amount, 2, '.', '') : '';
    }

    /**
     * ISO date with a trailing space so Excel keeps it as text and never
     * collapses a date column into "########".
     */
    private function csvDate(?\Carbon\CarbonInterface $date): string
    {
        return $date ? $date->format('Y-m-d').' ' : '';
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
            // UTF-8 BOM so Excel opens accented characters correctly.
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function settings(): View
    {
        return view('admin.billing.settings', [
            'feeRates' => FeeRate::active()->ordered()->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'tax_2551q_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::set('tax_2551q_rate', $request->tax_2551q_rate);

        ActivityLog::record(auth()->user(), 'billing.settings', 'Billing settings updated.');

        return back()->with('status', 'Billing settings saved.');
    }

    public function paymentSettings(): View
    {
        return view('admin.billing.payment-settings', [
            'gcashNumber' => Setting::get('gcash_number', ''),
            'gcashQrCode' => Setting::get('gcash_qr_code', ''),
            'bankAccounts' => Setting::get('bank_accounts', []),
            'supportedBanks' => SupportedBanks::all(),
        ]);
    }

    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'gcash_number' => ['nullable', 'string', 'max:30'],
            'gcash_qr_code' => ['nullable', 'file', 'image', 'max:2048'],
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank_qr_code' => ['nullable', 'file', 'image', 'max:2048'],
            'bank_accounts.*.existing_bank_qr_code' => ['nullable', 'string'],
        ]);

        $bankAccounts = $request->input('bank_accounts', []);

        // Every bank row must be complete and its account number must match the
        // selected bank's format. A blank row left in the form blocks the save.
        $validator = Validator::make([], []);
        $validator->after(function ($validator) use ($bankAccounts) {
            foreach ($bankAccounts as $index => $account) {
                $prefix = 'bank_accounts.'.$index;

                $bankName = trim($account['bank_name'] ?? '');
                $accountNumber = trim($account['account_number'] ?? '');
                $accountName = trim($account['account_name'] ?? '');

                if ($bankName === '') {
                    $validator->errors()->add($prefix.'.bank_name', 'Bank name is required.');
                }

                if ($accountName === '') {
                    $validator->errors()->add($prefix.'.account_name', 'Account name is required.');
                }

                $bank = $bankName === '' ? null : SupportedBanks::bankFor($bankName);
                if ($bankName !== '' && $bank === null) {
                    $validator->errors()->add($prefix.'.bank_name', 'This bank is not supported. Choose one from the list.');
                }

                if ($accountNumber === '') {
                    $validator->errors()->add($prefix.'.account_number', 'Account number is required.');
                } else {
                    if (preg_match('/[^0-9\s\-]/', $accountNumber)) {
                        $validator->errors()->add($prefix.'.account_number', 'Account number must contain numbers only.');
                    } elseif ($bank !== null && ! SupportedBanks::isValidAccountNumber($accountNumber, $bank['slug'])) {
                        $validator->errors()->add($prefix.'.account_number', 'Enter a valid account number for this bank.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            // Nothing is saved and no existing payment settings are touched.
            // Old input is re-presented so the user can correct their values.
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $request->only(['gcash_number']);

        Setting::set('gcash_number', $validated['gcash_number'] ?? '');

        if ($request->hasFile('gcash_qr_code')) {
            $oldPath = Setting::get('gcash_qr_code');
            if ($oldPath) {
                Storage::disk('supabase')->delete($oldPath);
            }
            $path = $request->file('gcash_qr_code')->store('payment-images', 'supabase');
            Setting::set('gcash_qr_code', $path);
        }

        $bankAccounts = array_values($bankAccounts);

        foreach ($bankAccounts as $i => &$account) {
            $account['bank_name'] = trim($account['bank_name'] ?? '');
            $account['account_name'] = trim($account['account_name'] ?? '');
            $account['account_number'] = SupportedBanks::normalizeAccountNumber($account['account_number'] ?? '');

            if ($request->hasFile("bank_accounts.{$i}.bank_qr_code")) {
                if (! empty($account['existing_bank_qr_code'])) {
                    Storage::disk('supabase')->delete($account['existing_bank_qr_code']);
                }
                $path = $request->file("bank_accounts.{$i}.bank_qr_code")->store('payment-images', 'supabase');
                $account['bank_qr_code'] = $path;
            } else {
                // Keep existing QR code path if no new file uploaded
                $existing = $account['existing_bank_qr_code'] ?? '';
                $account['bank_qr_code'] = $existing;
            }
            unset($account['existing_bank_qr_code']);
        }
        unset($account);

        Setting::set('bank_accounts', $bankAccounts);

        ActivityLog::record(auth()->user(), 'billing.payment_settings_updated', 'Payment details settings updated.');

        return back()->with('status', 'Payment details saved.');
    }

    public function paymentImage(string $type, int $index = 0)
    {
        if ($type === 'gcash') {
            $path = Setting::get('gcash_qr_code');
        } else {
            $accounts = Setting::get('bank_accounts', []);
            $path = $accounts[$index]['bank_qr_code'] ?? null;
        }

        abort_unless($path && Storage::disk('supabase')->exists($path), 404);

        $temporaryUrl = Storage::disk('supabase')->temporaryUrl($path, now()->addMinutes(30));

        return redirect($temporaryUrl)->header('Cache-Control', 'public, max-age=86400');
    }

    public function storeFeeRate(Request $request): Response
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'label' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', 'in:professional_fee,bookkeeping_fee,post_closing_tb,inventory_list,other_attachment,data_entry'],
        ]);

        $feeRate = FeeRate::query()->create([
            'amount' => $validated['amount'],
            'label' => ($validated['label'] ?? null) ?: null,
            'category' => $validated['category'],
            'sort_order' => (int) FeeRate::query()->max('sort_order') + 1,
        ]);

        ActivityLog::record(auth()->user(), 'billing.fee_rate_added', "Added fee preset of {$validated['amount']} ({$validated['category']}).");

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Fee preset added.',
                'feeRate' => [
                    'id' => $feeRate->id,
                    'label' => $feeRate->label,
                    'category' => $feeRate->category,
                    'amount' => $feeRate->amount,
                    'money' => $feeRate->money(),
                ],
            ], 201);
        }

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
            'Content-Disposition' => 'attachment; filename="Egliane-Billing-Summary-'.Str::slug($periodLabel).'-'.now()->format('Y-m-d').'.xlsx"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($billings, $quarter, $year) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Grouped layout is driven by the shared matrix so the workbook
            // mirrors the approved summary design and cannot drift from the PDF.
            $this->writeSummaryWorkbook($sheet, $this->summaryMatrix($quarter, $year));

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setIncludeCharts(false);
            $writer->save('php://output');
        }, 200, $headers);
    }

    /**
     * Writes the grouped Billing Summary workbook:
     *
     *   row 1  CLIENT | FOR REMITTANCE (merged) | FOR FEE (merged) | TOTAL | GRAND TOTAL
     *   row 2  the individual BIR, Cash In, fee, subtotal and total columns
     *   rows 3+ one row per statement
     *   last   GRAND TOTAL row of column sums
     *
     * All column order, grouping and arithmetic come from BillingSummaryMatrix.
     */
    private function writeSummaryWorkbook($sheet, BillingSummaryMatrix $matrix): void
    {
        $navy = '111827';
        $remit = '1E4E8C';
        $fee = '0F766E';
        $remitWash = 'EAF1FB';
        $feeWash = 'E7F3F2';
        $grandWash = 'F1F3F7';
        $border = 'D8DEE7';

        $columns = $matrix->columns();
        $spans = $matrix->groupSpans();
        $rows = $matrix->rows();
        $totals = $matrix->columnTotals();

        $dataStart = 3;
        $dataEnd = $dataStart + count($rows) - 1;
        $totalRow = $dataEnd + 1;
        $lastCol = count($columns);

        // --- Row 1: group header -------------------------------------------
        $letter = fn (int $i): string => Coordinate::stringFromColumnIndex($i);

        $sheet->setCellValue('A1', 'Client');
        $sheet->mergeCells('A1:A2');

        $remitStart = 2;
        $remitEnd = $remitStart + $spans['remittance'] - 1;
        $sheet->setCellValue($letter($remitStart).'1', 'For Remittance');
        $sheet->mergeCells($letter($remitStart).'1:'.$letter($remitEnd).'1');

        $feeStart = $remitEnd + 1;
        $feeEnd = $feeStart + $spans['fee'] - 1;
        $sheet->setCellValue($letter($feeStart).'1', 'For Fee');
        $sheet->mergeCells($letter($feeStart).'1:'.$letter($feeEnd).'1');

        foreach (['total' => $lastCol - 1, 'grand_total' => $lastCol] as $key => $index) {
            $sheet->setCellValue($letter($index).'1', $columns[$index - 1]['label']);
            $sheet->mergeCells($letter($index).'1:'.$letter($index).'2');
        }

        // --- Row 2: individual column headers -------------------------------
        foreach ($columns as $i => $column) {
            if ($column['key'] === 'client') {
                continue;
            }

            $sheet->setCellValue($letter($i + 1).'2', $column['label']);
        }

        // --- Data rows ------------------------------------------------------
        foreach ($rows as $r => $row) {
            $rowNumber = $dataStart + $r;

            $sheet->setCellValue('A'.$rowNumber, $row['client'] !== '' ? $row['client'] : 'Client removed');

            foreach ($columns as $i => $column) {
                if ($column['key'] === 'client') {
                    continue;
                }

                $sheet->setCellValue(
                    $letter($i + 1).$rowNumber,
                    (float) ($row[$column['key']] ?? 0)
                );
            }
        }

        // --- Final GRAND TOTAL row -----------------------------------------
        $sheet->setCellValue('A'.$totalRow, 'GRAND TOTAL');

        foreach ($columns as $i => $column) {
            if ($column['key'] === 'client') {
                continue;
            }

            $sheet->setCellValue(
                $letter($i + 1).$totalRow,
                (float) ($totals[$column['key']] ?? 0)
            );
        }

        // --- Header styling -------------------------------------------------
        $headerRange = 'A1:'.$letter($lastCol).'2';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->getStyle('A1:'.$letter($lastCol).'1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB($navy);

        $sheet->getStyle($letter($remitStart).'1:'.$letter($remitEnd).'2')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB($remit);

        $sheet->getStyle($letter($feeStart).'1:'.$letter($feeEnd).'2')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB($fee);

        $sheet->getStyle('A1:A2')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB($navy);

        foreach ([$lastCol - 1, $lastCol] as $index) {
            $sheet->getStyle($letter($index).'1:'.$letter($index).'2')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB($navy);
        }

        $sheet->getStyle('A1:A2')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // --- Column widths ---------------------------------------------------
        $sheet->getColumnDimension('A')->setWidth(26);

        foreach ($columns as $i => $column) {
            if ($column['key'] === 'client') {
                continue;
            }

            $columnLetter = $letter($i + 1);
            $sheet->getColumnDimension($columnLetter)->setWidth($column['subtotal'] ? 15 : 13);
        }

        if (! $rows) {
            $sheet->setCellValue('A3', 'No billing statements found for this period.');
            $sheet->getStyle('A3:'.$letter($lastCol).'3')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // --- Number formats and body styling ---------------------------------
        $bodyRange = $letter(2).$dataStart.':'.$letter($lastCol).$totalRow;
        $sheet->getStyle($bodyRange)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle($bodyRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        $allBorders = $sheet->getStyle('A1:'.$letter($lastCol).$totalRow)->getBorders();
        $allBorders->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setRGB($border);
        $allBorders->getInside()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR)
            ->getColor()->setRGB($border);

        $sheet->getStyle('A'.$dataStart.':A'.$totalRow)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);

        $this->tintSummaryColumn($sheet, $letter($remitEnd), $dataStart, $totalRow, $remitWash);
        $this->tintSummaryColumn($sheet, $letter($feeEnd), $dataStart, $totalRow, $feeWash);
        $this->tintSummaryColumn($sheet, $letter($lastCol), $dataStart, $totalRow, $grandWash);

        // --- Final total row -------------------------------------------------
        $sheet->getStyle('A'.$totalRow.':'.$letter($lastCol).$totalRow)->getFont()->setBold(true);

        $totalColors = [
            $remitEnd => $remit,
            $feeEnd => $fee,
            $lastCol => $navy,
            $lastCol - 1 => $navy,
        ];

        foreach ($totalColors as $index => $color) {
            $sheet->getStyle($letter($index).$totalRow)->getFont()->getColor()->setRGB($color);
        }

        $sheet->getStyle('A'.$totalRow.':'.$letter($lastCol).$totalRow)->getBorders()
            ->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
            ->getColor()->setRGB($navy);

        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->getRowDimension(2)->setRowHeight(30);

        // Lock both header rows and the client column.
        $sheet->freezePane('B3');
    }

    /**
     * Applies the soft category wash to one money column, leaving the value
     * readable while still separating remittance, fee and grand-total bands.
     */
    private function tintSummaryColumn($sheet, string $letter, int $dataStart, int $totalRow, string $color): void
    {
        $sheet->getStyle($letter.$dataStart.':'.$letter.$totalRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB($color);
    }

    /**
     * Formats a generated summary spreadsheet for readability in Excel:
     * auto-sized column widths, wrapped text, bold centered header row,
     * frozen header, and money columns as plain two-decimal numbers.
     */
    private function polishSummarySheet($sheet, array $headers, int $lastRow): void
    {
        $headerRange = Coordinate::stringFromColumnIndex(1).'1:'.Coordinate::stringFromColumnIndex(count($headers)).'1';
        $sheet->getStyle($headerRange)
            ->getFont()
            ->setBold(true);
        $sheet->getStyle($headerRange)
            ->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        foreach ($headers as $col => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);

            // Money columns: everything after the Client column.
            if ($col >= 1 && $lastRow >= 2) {
                $sheet->getStyle("{$colLetter}2:{$colLetter}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }

            // Auto-size to the widest cell (header or data), capped.
            $width = mb_strlen((string) $header) + 2;
            for ($r = 2; $r <= $lastRow; $r++) {
                $width = max($width, mb_strlen((string) $sheet->getCell("{$colLetter}{$r}")->getValue()) + 2);
            }
            $sheet->getColumnDimension($colLetter)->setWidth(min($width, 42));

            if ($col === 0) {
                $sheet->getStyle("{$colLetter}2:{$colLetter}{$lastRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            }
        }

        $sheet->freezePane('A2');
    }

    public function exportSummaryPdf(Request $request): Response
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

        // Collect all form types for dynamic columns
        $allFormTypes = $billings->flatMap(fn (Billing $b) => $b->lineItems->pluck('form_type')->filter())->unique()->values()->toArray();
        sort($allFormTypes);

        $pdf = Pdf::loadView('admin.billing.summary-pdf', [
            'billings' => $billings,
            'periodLabel' => $periodLabel,
            'allFormTypes' => $allFormTypes,
            'matrix' => $this->summaryMatrix($quarter, $year),
        ])->setPaper('a4', 'landscape');

        $filename = 'Egliane-Billing-Summary-'.Str::slug($periodLabel).'-'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    public function printBatch(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'min:1', 'max:60'],
            'ids.*' => ['integer'],
            'paper' => ['nullable', 'string', 'in:a4,letter'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'year' => ['nullable', 'integer'],
        ]);

        $paperSize = strtolower($validated['paper'] ?? 'a4');

        if (! empty($validated['ids'])) {
            $billings = collect($validated['ids'])
                ->map(fn ($id) => Billing::with(['client.profile', 'lineItems'])->find($id))
                ->filter()
                ->values();
        } else {
            $billings = $this->getFilteredBillings(
                $validated['quarter'] ?? null,
                (int) ($validated['year'] ?? now()->year)
            )->take(60);
        }

        if ($billings->isEmpty()) {
            abort(404, 'No billing statements found.');
        }

        ActivityLog::record(
            auth()->user(),
            'admin.billing_batch_printed',
            self::batchPrintLogMessage($billings, $paperSize)
        );

        // Two receipts sit side by side in one pair (Taxpayer's Copy +
        // Egliane's Copy). Pairs flow down the page at their NATURAL height —
        // nothing is clipped — so density is chosen to fit the ENTIRE batch on
        // one page. The reserved payment-details height feeds pairHeightMm()
        // through the estimator, and the block itself renders in-flow.
        $pageHeightMm = ['a4' => 297.0, 'letter' => 279.4][$paperSize];
        $pageContentMm = round($pageHeightMm - 20.0 - 2.0, 2); // 10mm @page margins + safety

        [$density, $overflowIds] = self::chooseBatchDensity($billings, $pageContentMm);

        $payments = \App\Support\BillingPaymentDetails::forPdf();

        $pdf = Pdf::loadView('admin.billing.statements-pdf', [
            'billings' => $billings,
            'gcashNumber' => Setting::get('gcash_number', ''),
            'bankAccounts' => Setting::get('bank_accounts', []),
            'payments' => $payments,
            'paperSize' => $paperSize,
            'density' => $density,
            'overflowIds' => $overflowIds,
        ])->setPaper($paperSize, 'portrait');

        return $pdf->stream('Egliane-Billing-Statements-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Estimated natural height of one statement receipt in pt at the given
     * density tier (normal/compact/tiny), mirroring the PDF template metrics.
     */
    public static function estimateStatementHeightPt(Billing $billing, string $density): float
    {
        $scale = ['normal' => 1.0, 'compact' => 0.87, 'tiny' => 0.75][$density] ?? 1.0;

        $items = $billing->lineItems->filter(fn ($i) => (float) $i->amount != 0.0)->count();
        $cats = $billing->lineItems->pluck('category')->unique()->count();

        return (52.5 + 9.1 * $cats + 10.1 * $items) * $scale + 8.5; // + cell padding
    }

    /**
     * Natural height (mm) of one full receipt pair: the statement body plus the
     * in-cell payment-details block plus the cell's vertical padding. The batch
     * template renders pairs at their natural height (no clipping), so this is
     * the figure used to decide whether a batch fits a single page.
     */
    public static function pairHeightMm(Billing $billing, string $density, ?array $payments = null): float
    {
        $payments ??= \App\Support\BillingPaymentDetails::forPdf();
        $statementMm = self::estimateStatementHeightPt($billing, $density) * 25.4 / 72;
        $payMm = \App\Support\BillingPaymentDetails::blockHeightMm($payments);

        return round(($statementMm + $payMm + self::CELL_VERTICAL_PAD_MM) * self::PAIR_HEIGHT_SAFETY, 2);
    }

    /**
     * Pick the largest type tier at which the WHOLE batch fits on one page.
     * Pairs render at natural height (nothing is ever clipped), so this is a
     * page-fit decision based on the summed pair heights plus the inter-pair
     * gap. Statements whose smallest pair still exceeds a full page are flagged
     * so the template can note that they continue onto a second page.
     *
     * @return array{0: string, 1: array<int>} density + offending billing ids
     */
    public static function chooseBatchDensity($billings, float $pageContentMm, float $pairGapMm = 3.5): array
    {
        $billings = collect($billings)->values();

        foreach (['normal', 'compact', 'tiny'] as $density) {
            $total = 0.0;
            foreach ($billings as $billing) {
                $total += self::pairHeightMm($billing, $density);
            }
            $total += max($billings->count() - 1, 0) * $pairGapMm;

            if ($total <= $pageContentMm) {
                return [$density, []];
            }
        }

        $overflowIds = $billings
            ->filter(fn (Billing $b) => self::pairHeightMm($b, 'tiny') > $pageContentMm)
            ->pluck('id')
            ->all();

        return ['tiny', $overflowIds];
    }

    private static function batchPrintLogMessage($billings, string $paperSize): string
    {
        $msg = 'Printed a batch of '.$billings->count().' billing statement(s) on '.strtoupper($paperSize).' paper.';

        $pageContentMm = round(['a4' => 297.0, 'letter' => 279.4][$paperSize] - 22.0, 2);
        [, $overflowIds] = self::chooseBatchDensity($billings, $pageContentMm);

        if ($overflowIds) {
            $msg .= ' Statements taller than one page: #'.implode(', #', $overflowIds).'.';
        }

        return $msg;
    }

    /**
     * Grouped Billing Summary matrix for a period. The matrix only arranges
     * existing line items into the workbook's row/column layout; it performs no
     * database writes and does not change how any amount is derived.
     */
    private function summaryMatrix(?int $quarter, int $year): BillingSummaryMatrix
    {
        return BillingSummaryMatrix::make($this->getFilteredBillings($quarter, $year));
    }

    private function getFilteredBillings(?int $quarter, int $year): Collection
    {
        return Billing::with('lineItems')
            ->with('client.birFormStatuses')
            ->where('year', $year)
            ->whereIn('status', Billing::ACTIVE_STATUSES)
            ->when($quarter, fn ($query) => $query->where('quarter', $quarter))
            ->orderBy('quarter')
            ->get()
            ->sortBy(fn (Billing $b) => strtolower($b->client?->business_name ?: $b->client?->name))
            ->values();
    }

    private function buildPeriodLabel(?int $quarter, int $year): string
    {
        if ($quarter) {
            return Billing::QUARTERS[$quarter].' Quarter '.$year.' Billing';
        }

        return 'All Quarters '.$year.' Billing';
    }

    private function logBillingExport(string $format, int $count, ?int $quarter, int $year): void
    {
        $period = $quarter ? 'Q'.$quarter.' '.$year : 'All Quarters '.$year;

        ActivityLog::record(
            auth()->user(),
            'admin.billing_summary_exported',
            "Exported billing summary as {$format} ({$count} records, {$period})."
        );
    }
}
