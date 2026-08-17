<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Filing;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $year = now()->year;

        return view('client.dashboard', [
            'profile' => $user->getClientProfile(),
            'stats' => [
                'income' => $user->transactions()->where('type', 'income')->whereYear('transaction_date', $year)->sum('amount'),
                'expenses' => $user->transactions()->where('type', 'expense')->whereYear('transaction_date', $year)->sum('amount'),
                'transactions' => $user->transactions()->count(),
                'pendingFilings' => $user->filings()->where('status', Filing::STATUS_PENDING)->count(),
            ],
            'recentTransactions' => $user->transactions()->latest('transaction_date')->limit(5)->get(),
            'recentFilings' => $user->filings()->latest()->limit(5)->get(),
        ]);
    }
}
