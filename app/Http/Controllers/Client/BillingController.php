<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $billings = $user->billings()
            ->latest('year')
            ->latest('quarter')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $summary = $user->billings()->get()->reduce(
            function (array $carry, Billing $billing): array {
                $carry['billed'] += (float) $billing->total;
                if ($billing->isPaid()) {
                    $carry['paid'] += (float) $billing->total;
                }

                return $carry;
            },
            ['billed' => 0.0, 'paid' => 0.0]
        );

        $year = Billing::currentYear();
        $quarter = Billing::currentQuarter();

        $entryBilling = $user->billings()
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->latest('id')
            ->first();

        return view('client.billing.index', [
            'billings' => $billings,
            'summary' => $summary,
            'entryBilling' => $entryBilling,
            'currentQuarter' => $quarter,
            'currentYear' => $year,
            'years' => [$year - 1, $year, $year + 1],
            'rate' => (float) Setting::get('tax_2551q_rate', 3),
        ]);
    }

    public function submitSales(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'quarter' => ['required', 'integer', 'between:1,4'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'sales' => ['required', 'numeric', 'min:0'],
            'cash_in' => ['nullable', 'numeric', 'min:0'],
        ]);

        $quarter = (int) $validated['quarter'];
        $year = (int) $validated['year'];
        $rate = (float) Setting::get('tax_2551q_rate', 3);
        $sales = (float) $validated['sales'];
        $cashIn = (float) ($validated['cash_in'] ?? 0);
        $tax2551q = round($sales * $rate / 100, 2);

        $billing = $user->billings()
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->latest('id')
            ->first();

        if ($billing && $billing->isPaid()) {
            return back()->withErrors(['sales' => 'This billing period is already paid and can no longer be updated.']);
        }

        if (! $billing) {
            $billing = new Billing;
            $billing->client_id = $user->id;
            $billing->quarter = $quarter;
            $billing->year = $year;
            $billing->period_label = strtoupper(Billing::QUARTERS[$quarter]).' QUARTER '.$year.' BILLING';
            $billing->status = Billing::STATUS_PENDING;
            $billing->created_by = $user->id;
            $billing->due_date = Billing::defaultDueDate($quarter, $year)->toDateString();
        }

        $billing->sales = $sales;
        $billing->cash_in = $cashIn;
        $billing->rate_2551q = $rate;
        $billing->tax_2551q = $tax2551q;
        $billing->sales_submitted_at = now();
        $billing->updated_by = $user->id;
        $billing->recomputeTotal();
        $billing->syncStatus();
        $billing->save();

        Notification::resolveGroup($user->id, "billing_missing_sales:{$user->id}:{$year}:{$quarter}");

        return back()->with('status', "Sales submitted for {$billing->periodTitle()}. The final total will be set once Egliane adds the 1701Q and professional fees.");
    }

    public function periodData(Request $request): JsonResponse
    {
        $year = (int) $request->get('year');
        $quarter = (int) $request->get('quarter');

        $billing = auth()->user()->billings()
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->latest('id')
            ->first();

        return response()->json([
            'sales' => $billing?->sales,
            'cash_in' => $billing?->cash_in,
            'submitted' => $billing?->sales_submitted_at !== null,
            'paid' => (bool) $billing?->isPaid(),
            'total' => $billing?->total,
        ]);
    }

    public function show(Billing $billing): View
    {
        abort_unless($billing->client_id === auth()->id(), 403);

        return view('client.billing.show', compact('billing'));
    }
}
