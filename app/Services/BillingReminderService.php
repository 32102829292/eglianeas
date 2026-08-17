<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;

class BillingReminderService
{
    /**
     * Remind clients who have not yet submitted their Sales for the current
     * quarter. Runs on the scheduler daily and keeps bumping the same grouped
     * notification until the client submits.
     */
    public static function remindMissingSales(): int
    {
        $year = Billing::currentYear();
        $quarter = Billing::currentQuarter();
        $sent = 0;

        $clients = User::query()->where('role', User::ROLE_CLIENT)->get();

        foreach ($clients as $client) {
            $submitted = Billing::query()
                ->where('client_id', $client->id)
                ->where('year', $year)
                ->where('quarter', $quarter)
                ->whereNotNull('sales_submitted_at')
                ->exists();

            if ($submitted) {
                continue;
            }

            $group = "billing_missing_sales:{$client->id}:{$year}:{$quarter}";
            $period = Billing::QUARTERS[$quarter].' Quarter '.$year;

            Notification::remind(
                $client->id,
                $group,
                'Sales submission needed',
                "Please submit your Sales for the {$period} billing.",
                'billing_missing',
                route('client.billing.index')
            );

            $sent++;
        }

        return $sent;
    }

    /**
     * Remind clients about unpaid bills starting one week before the due date,
     * escalating the wording once the bill is past due. Auto-marks past-due
     * bills as overdue so the Collections page stays accurate. Runs daily and
     * keeps bumping until the bill is marked paid.
     */
    public static function remindBillsDue(): int
    {
        $sent = 0;

        $due = Billing::query()
            ->whereIn('status', [Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(7)->toDateString())
            ->get();

        foreach ($due as $billing) {
            $overdue = $billing->due_date->lt(now()->startOfDay());

            if ($overdue && $billing->status !== Billing::STATUS_OVERDUE) {
                $billing->status = Billing::STATUS_OVERDUE;
                $billing->save();
            }

            $group = "billing_due:{$billing->id}";
            $title = $overdue ? 'Billing overdue' : 'Billing payment due';
            $body = $overdue
                ? "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is now overdue. Please settle it at your earliest convenience."
                : "Your {$billing->periodTitle()} billing of {$billing->money($billing->total)} is due on {$billing->due_date->format('F j, Y')}.";

            Notification::remind(
                $billing->client_id,
                $group,
                $title,
                $body,
                $overdue ? 'billing_overdue' : 'billing_due',
                route('client.collections.index')
            );

            $sent++;
        }

        return $sent;
    }
}
