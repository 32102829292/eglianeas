<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BillingStatementMail;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\BirFormStatus;
use App\Models\FeeRate;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
                'billing_count' => $client->billings->whereIn('status', Billing::ACTIVE_STATUSES)->count(),
                'total_billed' => $client->billings->whereIn('status', Billing::ACTIVE_STATUSES)->sum('total'),
                'total_paid' => $client->billings->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'status' => $this->clientStatus($client->billings),
            ])
            ->sortByDesc(fn (array $entry) => [$entry['billings']->count() > 0, $entry['status']])
            ->values();

        return view('admin.billing.index', [
            'entries' => $clients,
            'q' => $q,
            'stats' => [
                'billed' => (float) Billing::query()->whereIn('status', Billing::ACTIVE_STATUSES)->sum('total'),
                'collected' => (float) Billing::query()->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => (float) Billing::query()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'overdue' => Billing::query()->where('status', Billing::STATUS_OVERDUE)->count(),
            ],
        ]);
    }

    public function show(User $client): View
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $billings = $client->billings()
            ->with(['creator', 'lineItems'])
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
                'billed' => $client->billings()->whereIn('status', Billing::ACTIVE_STATUSES)->sum('total'),
                'paid' => $client->billings()->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
                'count' => $client->billings()->whereIn('status', Billing::ACTIVE_STATUSES)->count(),
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
        $rows[] = ['Business', $client->business_name ?: $client->name];
        $rows[] = ['Contact', $client->name.' <'.$client->email.'>'];
        $rows[] = [];
        $rows[] = ['Period', 'Due date', 'Cash-in', 'Total', 'Status', 'Paid at'];
        foreach ($billings as $billing) {
            $rows[] = [
                $billing->periodTitle(),
                $billing->due_date?->format('Y-m-d'),
                $billing->cash_in,
                $billing->total,
                $billing->statusLabel(),
                $billing->paid_at?->format('Y-m-d H:i'),
            ];
        }

        return $this->streamCsv($rows, Str::slug($client->business_name ?: $client->name).'-billing-'.now()->format('Y-m-d').'.csv');
    }

    public function create(Request $request): View
    {
        // Bug 1: pre-select a client when arriving from that client's billing page.
        $selectedClientId = null;
        $requestedClient = $request->query('client') ?: $request->query('client_id');
        if ($requestedClient) {
            $selectedClientId = (int) $requestedClient;
            $exists = User::where('id', $selectedClientId)->where('role', User::ROLE_CLIENT)->exists();
            if (! $exists) {
                $selectedClientId = null;
            }
        }

        // Bug 2: default quarter for the selected client's current year
        // (Q1 if none, otherwise the first unbilled quarter in sequence).
        $defaultQuarter = null;
        if ($selectedClientId) {
            $year = (int) now()->format('Y');
            $quarter = Billing::nextQuarterFor($selectedClientId, $year);
            if ($quarter > 0) {
                $defaultQuarter = $quarter;
            }
        }

        return view('admin.billing.create', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'feeRates' => FeeRate::active()->ordered()->get(),
            'billing' => new Billing,
            'selectedClientId' => $selectedClientId,
            'defaultQuarter' => $defaultQuarter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = Billing::STATUS_UNPAID;

        $quarter = $data['quarter'] ?? null;
        $year    = $data['year'] ?? null;

        if ($quarter !== null && $year !== null) {
            $duplicate = Billing::where('client_id', $data['client_id'])
                ->where('quarter', $quarter)
                ->where('year', $year)
                ->exists();

            if ($duplicate) {
                $periodName = Billing::QUARTERS[$quarter] . ' Quarter ' . $year;

                return back()->withInput()->withErrors([
                    'client_id' => "A billing statement for {$periodName} already exists for this client. Please edit the existing statement instead.",
                ]);
            }
        }

        $billing = new Billing($data);
        $billing->due_date = $this->resolvedDueDate($billing, $data['due_date'] ?? null);

        $billing->save();

        $this->syncLineItems($billing, $request);
        $billing->recomputeTotal();
        $billing->save();

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
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'feeRates' => FeeRate::active()->ordered()->get(),
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
        $billing->save();

        $this->syncLineItems($billing, $request);
        $billing->recomputeTotal();
        $billing->save();

        ActivityLog::record(auth()->user(), 'admin.billing_updated', "Updated {$billing->period_label} for {$billing->client?->name}.");

        return redirect()->route('admin.billing.show', $billing->client)->with('status', 'Billing record updated.');
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
        $label = $billing->period_label;
        $client = $billing->client;

        $billing->delete();

        ActivityLog::record(auth()->user(), 'admin.billing_deleted', "Deleted {$label}.");

        return redirect()->route('admin.billing.show', $client)->with('status', 'Billing record deleted.');
    }

    public function applicableForms(Request $request): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $forms = BirFormStatus::where('client_id', $request->client_id)
            ->where('applicable', true)
            ->pluck('form_type')
            ->values();

        return response()->json(['forms' => $forms]);
    }

    public function lastBilling(Request $request): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $lastBilling = Billing::with('lineItems')
            ->where('client_id', $request->client_id)
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->orderByDesc('id')
            ->first();

        if (! $lastBilling) {
            return response()->json(['line_items' => []]);
        }

        $lineItems = $lastBilling->lineItems->map(fn (BillingLineItem $item) => [
            'category'    => $item->category,
            'form_type'   => $item->form_type,
            'label'       => $item->label,
            'month'       => $item->month,
            'amount'      => $item->amount,
            'fee_rate_id' => $item->fee_rate_id,
        ])->values()->all();

        return response()->json([
            'period_title' => $lastBilling->periodTitle(),
            'line_items'   => $lineItems,
        ]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:users,id'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'period_label' => ['nullable', 'string', 'max:80'],
            'due_date' => ['nullable', 'date'],
            'cash_in' => ['nullable', 'numeric', 'min:0'],
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
                ? "Professional Fee — {$formType}" . ($month ? " ({$monthNames[$month]})" : '')
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

        $rows = [
            ['Business', $client?->business_name ?: $client?->name],
            ['Contact', $client ? "{$client->name} <{$client->email}>" : ''],
            [],
            [strtoupper($billing->periodTitle())],
            [],
        ];

        $grouped = $billing->lineItems->groupBy('category');

        foreach ($grouped as $category => $items) {
            $rows[] = [BillingLineItem::CATEGORIES[$category] ?? strtoupper($category)];
            foreach ($items as $item) {
                $rows[] = [$item->label, number_format($item->amount, 2)];
            }
        }

        if ((float) ($billing->cash_in ?? 0) > 0) {
            $rows[] = ['CASH IN', number_format($billing->cash_in, 2)];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', number_format($billing->total ?? 0, 2)];
        $rows[] = ['STATUS', $billing->statusLabel()];
        $rows[] = ['DUE DATE', $billing->due_date?->format('F j, Y') ?? ''];
        $rows[] = ['PAID AT', $billing->paid_at?->format('F j, Y') ?? ''];

        return $rows;
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
        ]);
    }

    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gcash_number' => ['nullable', 'string', 'max:30'],
            'gcash_qr_code' => ['nullable', 'file', 'image', 'max:2048'],
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:100'],
            'bank_accounts.*.account_number' => ['nullable', 'string', 'max:50'],
            'bank_accounts.*.account_name' => ['nullable', 'string', 'max:100'],
            'bank_accounts.*.bank_qr_code' => ['nullable', 'file', 'image', 'max:2048'],
            'bank_accounts.*.existing_bank_qr_code' => ['nullable', 'string'],
        ]);

        Setting::set('gcash_number', $validated['gcash_number'] ?? '');

        if ($request->hasFile('gcash_qr_code')) {
            $oldPath = Setting::get('gcash_qr_code');
            if ($oldPath) {
                Storage::disk('supabase')->delete($oldPath);
            }
            $path = $request->file('gcash_qr_code')->store('payment-images', 'supabase');
            Setting::set('gcash_qr_code', $path);
        }

        $bankAccounts = $validated['bank_accounts'] ?? [];
        $bankAccounts = array_filter($bankAccounts, function ($account) {
            return ! empty(trim($account['bank_name'] ?? '')) || ! empty(trim($account['account_number'] ?? ''));
        });
        $bankAccounts = array_values($bankAccounts);

        foreach ($bankAccounts as $i => &$account) {
            $account['bank_name'] = trim($account['bank_name'] ?? '');
            $account['account_number'] = trim($account['account_number'] ?? '');
            $account['account_name'] = trim($account['account_name'] ?? '');

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

    public function storeFeeRate(Request $request): \Symfony\Component\HttpFoundation\Response
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
            'Content-Disposition' => "attachment; filename=\"Egliane-Billing-Summary-" . Str::slug($periodLabel) . '-' . now()->format('Y-m-d') . ".xlsx\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($billings, $periodLabel) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Build dynamic columns from applicable forms
            $allFormTypes = $billings->flatMap(fn (Billing $b) => $b->lineItems->pluck('form_type')->filter())->unique()->values()->toArray();
            sort($allFormTypes);

            $colHeaders = ['Client'];
            $colWidths = [24];

            // BIR Remittances columns
            foreach ($allFormTypes as $ft) {
                $colHeaders[] = "{$ft} (BIR)";
                $colWidths[] = 14;
            }
            $colHeaders[] = 'Cash In';
            $colWidths[] = 14;

            // Professional Fee columns
            foreach ($allFormTypes as $ft) {
                $colHeaders[] = "Fee — {$ft}";
                $colWidths[] = 14;
            }

            // Bookkeeping
            $colHeaders[] = 'Bookkeeping Fee';
            $colWidths[] = 14;

            // Post-Closing TB
            $colHeaders[] = 'Post-Closing TB';
            $colWidths[] = 16;

            // Inventory List
            $colHeaders[] = 'Inventory List';
            $colWidths[] = 16;

            // Other Attachment
            $colHeaders[] = 'Other Attachment';
            $colWidths[] = 16;

            // Data Entry
            $colHeaders[] = 'Data Entry';
            $colWidths[] = 14;

            $colHeaders[] = 'Total';
            $colWidths[] = 14;

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
                $lineItems = $billing->lineItems;
                $values = [$client?->business_name ?: $client?->name ?? ''];

                // BIR Remittances (sum across filing months within the period)
                foreach ($allFormTypes as $ft) {
                    $values[] = (float) $lineItems
                        ->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
                        ->where('form_type', $ft)->sum('amount');
                }

                // Cash In
                $values[] = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
                    ->whereNull('form_type')->sum('amount');

                // Professional Fees (sum across filing months within the period)
                foreach ($allFormTypes as $ft) {
                    $values[] = (float) $lineItems
                        ->where('category', BillingLineItem::CATEGORY_PROFESSIONAL_FEE)
                        ->where('form_type', $ft)->sum('amount');
                }

                // Bookkeeping
                $values[] = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_BOOKKEEPING_FEE)->sum('amount');

                // Post-Closing TB
                $values[] = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_POST_CLOSING_TB)->sum('amount');

                // Inventory List
                $values[] = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_INVENTORY_LIST)->sum('amount');

                // Other Attachment
                $values[] = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_OTHER_ATTACHMENT)->sum('amount');

                // Data Entry
                $values[] = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_DATA_ENTRY)->sum('amount');

                // Total
                $values[] = (float) $billing->total;

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

        // Collect all form types for dynamic columns
        $allFormTypes = $billings->flatMap(fn (Billing $b) => $b->lineItems->pluck('form_type')->filter())->unique()->values()->toArray();
        sort($allFormTypes);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.billing.summary-pdf', [
            'billings' => $billings,
            'periodLabel' => $periodLabel,
            'allFormTypes' => $allFormTypes,
        ])->setPaper('a4', 'landscape');

        $filename = 'Egliane-Billing-Summary-' . Str::slug($periodLabel) . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function printBatch(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:60'],
            'ids.*' => ['integer'],
            'paper' => ['nullable', 'string', 'in:a4,letter'],
        ]);

        $paperSize = strtolower($validated['paper'] ?? 'a4');

        $billings = collect($validated['ids'])
            ->map(fn ($id) => Billing::with(['client.profile', 'lineItems'])->find($id))
            ->filter()
            ->values();

        if ($billings->isEmpty()) {
            abort(404, 'No billing statements found.');
        }

        ActivityLog::record(
            auth()->user(),
            'admin.billing_batch_printed',
            self::batchPrintLogMessage($billings, $paperSize)
        );

        // Label-sheet grid: fixed equal-height cells (4 rows x 2 copies = 8
        // receipts/page). Slot height = page height minus @page margins minus
        // the payment-details footer reserve, split per row.
        $rowsPerPage = 4;
        $pageHeightMm = ['a4' => 297.0, 'letter' => 279.4][$paperSize];
        $rowSlotMm = round(($pageHeightMm - 20 - 10) / $rowsPerPage, 2);
        $slotPt = $rowSlotMm * 72 / 25.4;

        [$density, $overflowIds] = self::chooseBatchDensity($billings, $slotPt);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.billing.statements-pdf', [
            'billings' => $billings,
            'gcashNumber' => Setting::get('gcash_number', ''),
            'bankAccounts' => Setting::get('bank_accounts', []),
            'paperSize' => $paperSize,
            'rowSlotMm' => $rowSlotMm,
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
     * Pick the largest type tier at which EVERY statement fits its fixed cell;
     * statements that exceed the slot even at "tiny" are flagged for the
     * printed oversize warning instead of being silently truncated.
     *
     * @return array{0: string, 1: array<int>} density + offending billing ids
     */
    public static function chooseBatchDensity($billings, float $slotPt): array
    {
        $billings = collect($billings)->values();

        foreach (['normal', 'compact', 'tiny'] as $density) {
            if ($billings->every(fn (Billing $b) => self::estimateStatementHeightPt($b, $density) <= $slotPt)) {
                return [$density, []];
            }
        }

        $overflowIds = $billings
            ->filter(fn (Billing $b) => self::estimateStatementHeightPt($b, 'tiny') > $slotPt)
            ->pluck('id')
            ->all();

        return ['tiny', $overflowIds];
    }

    private static function batchPrintLogMessage($billings, string $paperSize): string
    {
        $msg = 'Printed a batch of '.$billings->count().' billing statement(s) on '.strtoupper($paperSize).' paper.';

        $slotPt = round((['a4' => 297.0, 'letter' => 279.4][$paperSize] - 30) / 4 * 72 / 25.4, 2);
        [, $overflowIds] = self::chooseBatchDensity($billings, $slotPt);

        if ($overflowIds) {
            $msg .= ' Oversized (truncated in print): #'.implode(', #', $overflowIds).'.';
        }

        return $msg;
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
