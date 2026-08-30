<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\ClientProfile;
use App\Models\DailySnapshot;
use App\Models\Filing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $dueBills = Billing::query()
            ->with('client')
            ->whereIn('status', [Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(7)->toDateString())
            ->latest('due_date')
            ->limit(8)
            ->get();

        $billingStatusCounts = Billing::query()
            ->whereIn('status', Billing::ACTIVE_STATUSES)
            ->selectRaw('status, count(*) as count, sum(total) as total')
            ->groupBy('status')
            ->pluck('count', 'status');

        $billingStatusTotals = Billing::query()
            ->whereIn('status', Billing::ACTIVE_STATUSES)
            ->selectRaw('status, count(*) as count, sum(total) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $clientStatusCounts = ClientProfile::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $businessTypeCounts = ClientProfile::query()
            ->selectRaw("COALESCE(NULLIF(business_type, ''), 'Unspecified') as bucket, count(*) as count")
            ->groupBy('bucket')
            ->orderByDesc('count')
            ->pluck('count', 'bucket');

        $lobCounts = ClientProfile::query()
            ->selectRaw("COALESCE(NULLIF(line_of_business, ''), 'Unspecified') as bucket, count(*) as count")
            ->groupBy('bucket')
            ->orderByDesc('count')
            ->pluck('count', 'bucket');

        $charts = [
            'billingStatus' => $this->barData(Billing::STATUSES, fn (string $key) => (int) ($billingStatusCounts[$key] ?? 0)),
            'clientStatus' => $this->barData(ClientProfile::STATUSES, fn (string $key) => (int) ($clientStatusCounts[$key] ?? 0)),
            'businessType' => $this->barData($businessTypeCounts->mapWithKeys(fn ($count, $bucket) => [$bucket => $bucket])->all(), fn ($key) => (int) ($businessTypeCounts[$key] ?? 0)),
            'lineOfBusiness' => $this->barData($lobCounts->mapWithKeys(fn ($count, $bucket) => [$bucket => $bucket])->all(), fn ($key) => (int) ($lobCounts[$key] ?? 0)),
        ];

        $snapshots = DailySnapshot::orderByDesc('date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        $categoryTotals = BillingLineItem::query()
            ->whereHas('billing', fn ($query) => $query->whereIn('status', Billing::ACTIVE_STATUSES))
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->sortByDesc(fn ($total) => $total);

        $topOutstanding = Billing::query()
            ->join('users', 'users.id', '=', 'billings.client_id')
            ->whereIn('billings.status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
            ->selectRaw('users.name as client_name, users.business_name, sum(billings.total) as total')
            ->groupBy('users.id', 'users.name', 'users.business_name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return view('admin.dashboard', [
            'paidCount' => (int) ($billingStatusCounts[Billing::STATUS_PAID] ?? 0),
            'pendingCount' => (int) ($billingStatusCounts[Billing::STATUS_PENDING] ?? 0),
            'unpaidCount' => (int) ($billingStatusCounts[Billing::STATUS_UNPAID] ?? 0),
            'overdueCount' => (int) ($billingStatusCounts[Billing::STATUS_OVERDUE] ?? 0),
            'snapshotRevenue' => $snapshots->pluck('revenue_collected')->values(),
            'snapshotNewBillings' => $snapshots->pluck('new_billings')->values(),
            'snapshotOverdue' => $snapshots->pluck('overdue_count')->values(),
            'snapshotLabels' => $snapshots->pluck('date')->map(fn ($d) => $d->format('M j'))->values(),
            'categoryChart' => [
                'labels' => $categoryTotals->keys()->map(fn ($key) => BillingLineItem::CATEGORIES[$key] ?? ucfirst(str_replace('_', ' ', $key)))->values(),
                'totals' => $categoryTotals->values(),
            ],
            'topOutstanding' => $topOutstanding,
            'stats' => [
                'clients' => User::query()->where('role', User::ROLE_CLIENT)->count(),
                'transactions' => Transaction::count(),
                'filings' => Filing::count(),
                'pendingFilings' => Filing::query()->where('status', Filing::STATUS_PENDING)->count(),
            ],
            'recentUsers' => User::query()->latest()->limit(8)->get(),
            'recentFilings' => Filing::query()->with('client')->latest()->limit(8)->get(),
            'recentActivity' => ActivityLog::query()->with('user')->latest()->limit(10)->get(),
            'dueBills' => $dueBills,
            'billingAlerts' => [
                'dueSoon' => $dueBills->where('due_date', '>=', now()->startOfDay())->count(),
                'overdue' => (int) ($billingStatusCounts[Billing::STATUS_OVERDUE] ?? 0),
                'outstanding' => (float) (
                    ($billingStatusTotals[Billing::STATUS_PENDING] ?? 0)
                    + ($billingStatusTotals[Billing::STATUS_UNPAID] ?? 0)
                    + ($billingStatusTotals[Billing::STATUS_OVERDUE] ?? 0)
                ),
            ],
            'analytics' => [
                'billingStatusCounts' => $billingStatusCounts,
                'billingStatusTotals' => $billingStatusTotals,
                'clientStatusCounts' => $clientStatusCounts,
                'charts' => $charts,
            ],
        ]);
    }

    private function barData(array $map, callable $countFor): Collection
    {
        $items = collect();

        foreach ($map as $key => $label) {
            $items->push([
                'label' => $label,
                'count' => $countFor($key),
            ]);
        }

        $max = max($items->pluck('count')->max(), 1);

        return $items->map(fn (array $item): array => [
            'label' => $item['label'],
            'count' => $item['count'],
            'pct' => round($item['count'] / $max * 100, 1),
        ]);
    }
}
