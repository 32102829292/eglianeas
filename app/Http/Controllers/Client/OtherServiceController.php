<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\OtherService;
use Illuminate\View\View;

class OtherServiceController extends Controller
{
    public function billing(): View
    {
        $user = auth()->user();

        $services = $user->otherServices()
            ->with('serviceType')
            ->latest('requested_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $all = $user->otherServices()->get();
        $summary = $all->reduce(
            function (array $carry, OtherService $service): array {
                $carry['billed'] += (float) $service->amount;
                if ($service->isPaid()) {
                    $carry['paid'] += (float) $service->amount;
                } else {
                    $carry['outstanding'] += (float) $service->amount;
                }

                return $carry;
            },
            ['billed' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0]
        );

        return view('client.other-services.billing', [
            'services' => $services,
            'summary' => $summary,
        ]);
    }

    public function collections(): View
    {
        $user = auth()->user();

        $services = $user->otherServices()
            ->with('serviceType')
            ->latest('requested_at')
            ->latest('id')
            ->get();

        $summary = $services->reduce(
            function (array $carry, OtherService $service): array {
                $carry['total'] += (float) $service->amount;
                if ($service->isPaid()) {
                    $carry['paid'] += (float) $service->amount;
                } else {
                    $carry['outstanding'] += (float) $service->amount;
                }

                return $carry;
            },
            ['total' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0]
        );

        return view('client.other-services.collections', [
            'services' => $services,
            'summary' => $summary,
        ]);
    }

    public function receipt(OtherService $otherService): View
    {
        abort_unless($otherService->client_id === auth()->id(), 403);

        $otherService->load('client', 'serviceType');

        return view('client.other-services.receipt', ['service' => $otherService]);
    }
}
