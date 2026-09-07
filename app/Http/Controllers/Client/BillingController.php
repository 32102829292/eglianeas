<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $available = Billing::billedPeriodsFor($user->id);
        $availableQuarters = Billing::dropdownPeriods($available);
        $activeQuarter = Billing::resolveViewableQuarter($available, $request->query('quarter'));

        $billings = $user->billings()
            ->activeOnly()
            ->forPeriod($activeQuarter)
            ->with('lineItems')
            ->latest('year')
            ->latest('quarter')
            ->latest('id')
            ->paginate(15)
            ->withQueryString(['quarter' => $activeQuarter->key()]);

        $quarterBilled = (float) $user->billings()->activeOnly()->forPeriod($activeQuarter)->sum('total');
        $quarterPaid = (float) $user->billings()->activeOnly()->forPeriod($activeQuarter)->where('status', Billing::STATUS_PAID)->sum('total');

        $globalUnpaid = $this->globalUnpaid($user->id);

        return view('client.billing.index', [
            'billings' => $billings,
            'activeQuarter' => $activeQuarter,
            'availableQuarters' => $availableQuarters,
            'quarterSummary' => [
                'billed' => $quarterBilled,
                'paid' => $quarterPaid,
                'outstanding' => $quarterBilled - $quarterPaid,
            ],
            'globalUnpaid' => $globalUnpaid,
            'summary' => [
                'billed' => (float) $user->billings()->activeOnly()->sum('total'),
                'paid' => (float) $user->billings()->activeOnly()->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $globalUnpaid,
            ],
        ]);
    }

    /**
     * Total unpaid balance across ALL of the client's active billings,
     * regardless of the quarter currently being viewed.
     */
    private function globalUnpaid(int $clientId): float
    {
        return (float) Billing::query()
            ->where('client_id', $clientId)
            ->activeOnly()
            ->where('status', '!=', Billing::STATUS_PAID)
            ->sum('total');
    }

    public function show(Billing $billing): View
    {
        abort_unless($billing->client_id === auth()->id(), 403);
        // Drafts are admin-only; reject direct access to one.
        abort_if($billing->isDraft(), 404);

        $billing->load('lineItems');

        return view('client.billing.show', compact('billing'));
    }
}
