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

        $billings = $user->billings()
            ->with('lineItems')
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

        return view('client.billing.index', [
            'billings' => $billings,
            'summary' => $summary,
        ]);
    }

    public function show(Billing $billing): View
    {
        abort_unless($billing->client_id === auth()->id(), 403);

        $billing->load('lineItems');

        return view('client.billing.show', compact('billing'));
    }
}
