<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;

class BillingReminderService
{
    /**
     * Minimum gap between reminders for the same billing, in days.
     */
    public const REMINDER_COOLDOWN_DAYS = 7;

    /**
     * Remind clients about unpaid bills starting one week before the due date,
     * escalating the wording once the bill is past due. Auto-marks past-due
     * bills as overdue so the Collections page stays accurate. Runs daily and
     * re-reminds at most once per cooldown window until the bill is paid.
     */
    public static function remindBillsDue(): int
    {
        $sent = 0;
        $cooldownStart = now()->subDays(self::REMINDER_COOLDOWN_DAYS);

        $due = Billing::query()
            ->whereIn('status', [Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(7)->toDateString())
            ->where(function ($q) use ($cooldownStart) {
                $q->whereNull('reminder_sent_at')
                    ->orWhere('reminder_sent_at', '<=', $cooldownStart);
            })
            ->get();

        foreach ($due as $billing) {
            $overdue = $billing->due_date->lt(now()->startOfDay());

            if ($overdue && $billing->status !== Billing::STATUS_OVERDUE) {
                $billing->status = Billing::STATUS_OVERDUE;
                $billing->save();

                ActivityLog::record(
                    null,
                    'billing.auto_overdue',
                    "Automatically marked {$billing->period_label} for {$billing->client?->name} as overdue."
                );
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

            $client = User::find($billing->client_id);
            if ($client) {
                PushNotificationService::send($client, $title, $body, route('client.collections.index'));
            }

            $billing->reminder_sent_at = now();
            $billing->save();

            $sent++;
        }

        return $sent;
    }

    /**
     * Remind active clients who have not submitted their latest monthly
     * bookkeeping data (the app's recurring client submission) for the current
     * period. Reuses the same in-app + push delivery as the bill reminders,
     * with the same cooldown so it fires at most once per cooldown window.
     */
    public static function remindMissingSales(): int
    {
        $sent = 0;
        $cooldownStart = now()->subDays(self::REMINDER_COOLDOWN_DAYS);

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (User $user) => $user->monthlySurveyDue());

        foreach ($clients as $client) {
            $group = "monthly_submission:{$client->id}";

            $latest = Notification::query()
                ->where('user_id', $client->id)
                ->where('group_key', $group)
                ->latest('id')
                ->first();

            if ($latest && $latest->created_at->gt($cooldownStart)) {
                continue;
            }

            $title = 'Monthly data due';
            $body = "We haven't received this month's bookkeeping data yet. Please submit it so we can keep your records up to date.";
            $link = route('client.dashboard');

            Notification::remind(
                $client->id,
                $group,
                $title,
                $body,
                'monthly_data_due',
                $link
            );

            PushNotificationService::send($client, $title, $body, $link);

            $sent++;
        }

        return $sent;
    }
}
