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

        $allBillings = $user->billings();
        $summary = [
            'total' => (float) $allBillings->clone()->sum('total'),
            'paid' => (float) $allBillings->clone()->where('status', Billing::STATUS_PAID)->sum('total'),
            'outstanding' => (float) $allBillings->clone()->where('status', '!=', Billing::STATUS_PAID)->sum('total'),
        ];

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