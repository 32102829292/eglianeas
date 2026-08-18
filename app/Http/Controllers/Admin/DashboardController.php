<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\ClientProfile;
use App\Models\Filing;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $year = Billing::currentYear();
        $quarter = Billing::currentQuarter();

        // --- Existing queries ---
        $missingSalesClients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->whereDoesntHave('billings', function ($query) use ($year, $quarter) {
                $query->where('year', $year)
                    ->where('quarter', $quarter)
                    ->whereNotNull('sales_submitted_at');
            })
            ->orderBy('name')
            ->get();

        $dueBills = Billing::query()
            ->with('client')
            ->whereIn('status', [Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(7)->toDateString())
            ->latest('due_date')
            ->limit(8)
            ->get();

        $billingStatusCounts = Billing::query()
            ->selectRaw('status, count(*) as count, sum(total) as total')
            ->groupBy('status')
            ->pluck('count', 'status');

        $billingStatusTotals = Billing::query()
            ->selectRaw('status, count(*) as count, sum(total) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $clientStatusCounts = ClientProfile::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $businessTypeCounts = ClientProfile::query()
            ->selectRaw('COALESCE(NULLIF(business_type, ""), "Unspecified") as bucket, count(*) as count')
            ->groupBy('bucket')
            ->orderByDesc('count')
            ->pluck('count', 'bucket');

        $lobCounts = ClientProfile::query()
            ->selectRaw('COALESCE(NULLIF(line_of_business, ""), "Unspecified") as bucket, count(*) as count')
            ->groupBy('bucket')
            ->orderByDesc('count')
            ->pluck('count', 'bucket');

        $charts = [
            'billingStatus' => $this->barData(Billing::STATUSES, fn (string $key) => (int) ($billingStatusCounts[$key] ?? 0)),
            'clientStatus' => $this->barData(ClientProfile::STATUSES, fn (string $key) => (int) ($clientStatusCounts[$key] ?? 0)),
            'businessType' => $this->barData($businessTypeCounts->mapWithKeys(fn ($count, $bucket) => [$bucket => $bucket])->all(), fn ($key) => (int) ($businessTypeCounts[$key] ?? 0)),
            'lineOfBusiness' => $this->barData($lobCounts->mapWithKeys(fn ($count, $bucket) => [$bucket => $bucket])->all(), fn ($key) => (int) ($lobCounts[$key] ?? 0)),
        ];

        // --- New: greeting ---
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        // --- New: total clients for sales rate ---
        $totalClients = User::query()->where('role', User::ROLE_CLIENT)->count();
        $submittedCount = $totalClients - $missingSalesClients->count();
        $salesRate = $totalClients > 0 ? round(($submittedCount / $totalClients) * 100) : 0;

        // --- New: monthly billing/collection trends (last 6 months) ---
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $monthlyBilling = Billing::query()
            ->selectRaw('YEAR(created_at) as y, MONTH(created_at) as m, SUM(total) as amount, COUNT(*) as count')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('y', 'm')
            ->orderBy('y')
            ->orderBy('m')
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::createFromDate($row->y, $row->m, 1)->format('M') => [
                    'amount' => (float) $row->amount,
                    'count' => (int) $row->count,
                ],
            ]);

        $monthlyCollected = Billing::query()
            ->selectRaw('YEAR(paid_at) as y, MONTH(paid_at) as m, SUM(total) as amount, COUNT(*) as count')
            ->where('status', Billing::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $sixMonthsAgo)
            ->groupBy('y', 'm')
            ->orderBy('y')
            ->orderBy('m')
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::createFromDate($row->y, $row->m, 1)->format('M') => [
                    'amount' => (float) $row->amount,
                    'count' => (int) $row->count,
                ],
            ]);

        // Build last-6-months labels and data
        $monthLabels = collect();
        for ($i = 5; $i >= 0; $i--) {
            $monthLabels->push(now()->subMonths($i)->format('M'));
        }
        $billingTrend = $monthLabels->map(fn ($m) => (float) ($monthlyBilling[$m]['amount'] ?? 0))->values();
        $collectionTrend = $monthLabels->map(fn ($m) => (float) ($monthlyCollected[$m]['amount'] ?? 0))->values();

        // --- New: recent notifications ---
        $recentNotifications = Notification::query()
            ->latest()
            ->limit(6)
            ->get();

        // --- New: client risk breakdown ---
        $clientRisks = [
            'overdue' => $dueBills->where('status', Billing::STATUS_OVERDUE)->pluck('client')->unique('id')->count(),
            'dueSoon' => $dueBills->where('status', Billing::STATUS_UNPAID)->pluck('client')->unique('id')->count(),
            'missingSales' => $missingSalesClients->count(),
            'current' => $totalClients - $clientStatusCounts->filter(fn ($v, $k) => in_array($k, ['current']))->sum(),
        ];

        return view('admin.dashboard', [
            'greeting' => $greeting,
            'greetingName' => $user->name,
            'stats' => [
                'clients' => $totalClients,
                'transactions' => Transaction::count(),
                'filings' => Filing::count(),
                'pendingFilings' => Filing::query()->where('status', Filing::STATUS_PENDING)->count(),
            ],
            'recentUsers' => User::query()->latest()->limit(8)->get(),
            'recentFilings' => Filing::query()->with('client')->latest()->limit(8)->get(),
            'recentActivity' => ActivityLog::query()->with('user')->latest()->limit(10)->get(),
            'missingSalesClients' => $missingSalesClients,
            'dueBills' => $dueBills,
            'missingYear' => $year,
            'missingQuarter' => $quarter,
            'billingAlerts' => [
                'missingSales' => $missingSalesClients->count(),
                'dueSoon' => $dueBills->where('due_date', '>=', now()->startOfDay())->count(),
                'overdue' => (int) ($billingStatusCounts[Billing::STATUS_OVERDUE] ?? 0),
                'outstanding' => (float) (
                    ($billingStatusTotals[Billing::STATUS_PENDING] ?? 0)
                    + ($billingStatusTotals[Billing::STATUS_UNPAID] ?? 0)
                    + ($billingStatusTotals[Billing::STATUS_OVERDUE] ?? 0)
                ),
            ],
            'salesRate' => $salesRate,
            'submittedCount' => $submittedCount,
            'analytics' => [
                'billingStatusCounts' => $billingStatusCounts,
                'billingStatusTotals' => $billingStatusTotals,
                'clientStatusCounts' => $clientStatusCounts,
                'charts' => $charts,
            ],
            'chartData' => [
                'monthLabels' => $monthLabels->values()->toArray(),
                'billingTrend' => $billingTrend->toArray(),
                'collectionTrend' => $collectionTrend->toArray(),
            ],
            'recentNotifications' => $recentNotifications,
            'clientRisks' => $clientRisks,
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
