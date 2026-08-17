<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(): View
    {
        $billings = auth()->user()->billings()
            ->latest('year')
            ->latest('quarter')
            ->latest('id')
            ->get();

        $summary = $billings->reduce(
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
            'billings' => $billings,
            'summary' => $summary,
        ]);
    }
}
