<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Support\Quarter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $available = Billing::paymentPeriodsFor($user->id);
        $availableQuarters = Billing::dropdownPeriods($available);
        $activeQuarter = Billing::resolveViewableQuarter($available, $request->query('quarter'));

        $collections = $user->billings()
            ->paidDuring($activeQuarter)
            ->latest('paid_at')
            ->latest('id')
            ->get();

        $quarterPaid = (float) $user->billings()->paidDuring($activeQuarter)->sum('total');

        $globalUnpaid = (float) $user->billings()
            ->activeOnly()
            ->where('status', '!=', Billing::STATUS_PAID)
            ->sum('total');

        $allBillings = $user->billings()->get();
        $summary = $allBillings->reduce(
            function (array $carry, Billing $billing): array {
                $carry['total'] += (float) $billing->total;
                if ($billing->isPaid()) {
                    $carry['paid'] += (float) $billing->total;
                } else {
                    $carry['outstanding'] += (float) $billing->total;
                }

                return $carry;
            },
            ['total' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0]
        );

        return view('client.collections.index', [
            'collections' => $collections,
            'activeQuarter' => $activeQuarter,
            'availableQuarters' => $availableQuarters,
            'quarterSummary' => ['paid' => $quarterPaid, 'count' => $collections->count()],
            'globalUnpaid' => $globalUnpaid,
            'summary' => $summary,
        ]);
    }
}