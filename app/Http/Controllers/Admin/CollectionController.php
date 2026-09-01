<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status');

        $billings = Billing::query()
            ->with('client')
            ->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $all = Billing::query()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->get();

        return view('admin.collections.index', [
            'billings' => $billings,
            'statuses' => Billing::STATUSES,
            'stats' => [
                'outstanding' => (float) $all->sum('total'),
                'overdueCount' => $all->where('status', Billing::STATUS_OVERDUE)->count(),
                'dueSoon' => $all->filter(fn (Billing $billing) => $billing->status === Billing::STATUS_UNPAID
                    && $billing->due_date !== null
                    && $billing->due_date->lte(now()->addDays(7)))->count(),
                'pendingCount' => $all->where('status', Billing::STATUS_PENDING)->count(),
            ],
            'activeStatus' => $status,
        ]);
    }

    public function remind(Billing $billing): RedirectResponse
    {
        abort_unless(in_array($billing->status, [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE], true), 422);

        $overdue = $billing->isOverdue();

        Notification::remind(
            $billing->client_id,
            "billing_due:{$billing->id}",
            $overdue ? 'Billing overdue' : 'Billing payment due',
            $overdue
                ? "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is now overdue. Please settle it at your earliest convenience."
                : "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is due on {$billing->due_date?->format('F j, Y')}.",
            $overdue ? 'billing_overdue' : 'billing_due',
            route('client.collections.index')
        );

        $client = User::find($billing->client_id);
        if ($client) {
            PushNotificationService::send(
                $client,
                $overdue ? 'Billing overdue' : 'Billing payment due',
                $overdue
                    ? "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is now overdue."
                    : "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is due on {$billing->due_date?->format('F j, Y')}.",
                route('client.collections.index')
            );
        }

        ActivityLog::record(auth()->user(), 'admin.collection_reminded', "Sent a manual payment reminder for {$billing->period_label} to {$billing->client?->name}.");

        return back()->with('status', 'Payment reminder sent to the client.');
    }
}
