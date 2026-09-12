<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;
use App\Mail\BillingStatementMail;
use App\Services\PushNotificationService;
use App\Support\Quarter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $status = $request->get('status');
        $activeQuarter = Quarter::fromKey((string) $request->get('quarter'));

        // Canonicalize empty filter params server-side (see BillingController).
        if ($redirect = $this->canonicalCollectionQuery($request, $status, $activeQuarter)) {
            return $redirect;
        }

        $billings = Billing::query()
            ->with('client')
            ->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->when($activeQuarter, fn ($query) => $query
                ->where('year', $activeQuarter->year)
                ->where('quarter', $activeQuarter->quarter))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $statsQuery = Billing::query()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE]);

        return view('admin.collections.index', [
            'billings' => $billings,
            'statuses' => Billing::STATUSES,
            'activeQuarter' => $activeQuarter,
            'availableQuarters' => Billing::filterQuarters(),
            'stats' => [
                'outstanding' => (float) $statsQuery->clone()->sum('total'),
                'overdueCount' => $statsQuery->clone()->where('status', Billing::STATUS_OVERDUE)->count(),
                'dueSoon' => $statsQuery->clone()
                    ->where('status', Billing::STATUS_UNPAID)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<=', now()->addDays(7))
                    ->count(),
                'pendingCount' => $statsQuery->clone()->where('status', Billing::STATUS_PENDING)->count(),
            ],
            'activeStatus' => $status,
        ]);
    }

    /**
     * Redirect empty "quarter"/"status" GET params to the clean equivalent URL
     * so filters never persist "?status=" cruft in address bars and links.
     */
    private function canonicalCollectionQuery(Request $request, mixed $status, ?Quarter $activeQuarter): ?RedirectResponse
    {
        $query = $request->query();

        // Unexpected extra parameters: leave the URL alone to avoid data loss.
        if (count(array_diff_key($query, array_flip(['quarter', 'status', 'page']))) > 0) {
            return null;
        }

        // Empty query params arrive as '' before the request goes through the
        // ConvertEmptyStringsToNull middleware, then become null afterward —
        // treat both as empty.
        $emptyFilterKey = collect($query)
            ->filter(fn ($value) => $value === null || (is_string($value) && trim($value) === ''))
            ->keys()
            ->intersect(['quarter', 'status'])
            ->isNotEmpty();

        if (! $emptyFilterKey) {
            return null;
        }

        $params = [];
        if ($activeQuarter) {
            $params['quarter'] = $activeQuarter->key();
        }
        if (is_string($status) && $status !== '') {
            $params['status'] = $status;
        }
        if (($page = $query['page'] ?? null) !== null && trim((string) $page) !== '') {
            $params['page'] = $page;
        }

        return redirect()->route('admin.collections.index', $params ?: null);
    }

    public function remind(Billing $billing): RedirectResponse
    {
        if (! in_array($billing->status, [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE], true)) {
            return back()->with('error', 'Reminder not sent: this billing is no longer awaiting payment.');
        }

        $client = User::find($billing->client_id);
        $recipient = $client?->email;

        if (! $client || ! $recipient) {
            return back()->with('error', 'Reminder not sent: this client has no registered email address.');
        }

        $overdue = $billing->isOverdue();
        $title = $overdue ? 'Billing overdue' : 'Billing payment due';
        $emailBody = $overdue
            ? "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is now overdue. Please settle it at your earliest convenience."
            : "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is due on {$billing->due_date?->format('F j, Y')}.";
        $pushBody = $overdue
            ? "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is now overdue."
            : "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is due on {$billing->due_date?->format('F j, Y')}.";

        try {
            Mail::to($recipient)->send(new BillingStatementMail($billing->loadMissing(['client.profile', 'lineItems'])));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Reminder not sent: the email could not be delivered. Please try again later.');
        }

        Notification::remind(
            $billing->client_id,
            "billing_due:{$billing->id}",
            $title,
            $emailBody,
            $overdue ? 'billing_overdue' : 'billing_due',
            route('client.collections.index')
        );

        PushNotificationService::send(
            $client,
            $title,
            $pushBody,
            route('client.collections.index')
        );

        ActivityLog::record(auth()->user(), 'admin.collection_reminded', "Sent a manual payment reminder (email to {$recipient}) for {$billing->period_label} to {$billing->client?->name}.");

        return back()->with('status', 'Payment reminder sent to the client.');
    }
}
