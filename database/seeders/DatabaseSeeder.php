<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Billing;
use App\Models\BirFormStatus;
use App\Models\ClientProfile;
use App\Models\Filing;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $pin = '1234';

        User::query()->where('role', 'staff')->delete();

        $admin = User::firstOrCreate(
            ['email' => 'admin@eglianeas.com'],
            [
                'name' => 'Egliane Admin',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ]
        );
        $admin->pin = Hash::make($pin);
        $admin->pin_set_at = now();
        $admin->save();

        $client = User::firstOrCreate(
            ['email' => 'client@eglianeas.com'],
            [
                'name' => 'Juan Dela Cruz',
                'role' => User::ROLE_CLIENT,
                'business_name' => 'Acme Trading Co.',
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ]
        );
        $client->pin = Hash::make($pin);
        $client->pin_set_at = now();
        $client->save();

        if (empty($client->client_code)) {
            $client->client_code = User::generateClientCode();
            $client->save();
        }

        ClientProfile::query()->firstOrCreate(
            ['user_id' => $client->id],
            [
                'business_type' => 'Sole Proprietorship',
                'line_of_business' => 'Retail & Wholesale',
                'bir_registration_type' => 'Non-VAT',
                'business_address' => '123 Rizal Avenue, Brgy. San Isidro, Quezon City',
                'latitude' => 14.6327,
                'longitude' => 121.0332,
                'contact_no' => '+63 917 555 1234',
                'second_contact_no' => '+63 917 555 4321',
                'second_email' => 'maria.dc@acmetrading.ph',
                'birth_date' => now()->subYears(38),
                'tin_no' => '123-456-789-000',
                'mother_maiden_name' => 'Maria Santos',
                'father_name' => 'Pedro Dela Cruz',
                'status' => ClientProfile::STATUS_CURRENT,
                'payment_status' => 'paid',
                'date_started' => now()->subMonths(8),
            ]
        );

        Notification::query()->firstOrCreate(
            ['user_id' => $client->id, 'title' => 'Welcome to Egliane'],
            [
                'body' => 'Your account is set up and ready. Keep your profile and BIR forms up to date.',
                'type' => 'welcome',
                'link' => '/client/dashboard',
                'read_at' => null,
            ]
        );

        Notification::query()->firstOrCreate(
            ['user_id' => $client->id, 'title' => 'Income tax return due soon'],
            [
                'body' => 'Your Income Tax Return is due within 10 days. Make sure your records are submitted.',
                'type' => 'filing_due',
                'link' => '/client/billing',
                'read_at' => null,
            ]
        );

        Transaction::query()->firstOrCreate(
            ['client_id' => $client->id, 'reference' => 'SEED-1'],
            [
                'title' => 'Product sales',
                'type' => 'income',
                'category' => 'Sales',
                'amount' => 84250.00,
                'transaction_date' => now()->subDays(3),
                'created_by' => $admin->id,
            ]
        );

        Transaction::query()->firstOrCreate(
            ['client_id' => $client->id, 'reference' => 'SEED-2'],
            [
                'title' => 'Office supplies',
                'type' => 'expense',
                'category' => 'Supplies',
                'amount' => 12800.00,
                'transaction_date' => now()->subDays(2),
                'created_by' => $admin->id,
            ]
        );

        Filing::query()->firstOrCreate(
            ['client_id' => $client->id, 'type' => 'Income Tax Return', 'period' => now()->format('Y')],
            [
                'due_date' => now()->addDays(10),
                'status' => Filing::STATUS_PENDING,
                'notes' => 'For review',
            ]
        );

        Filing::query()->firstOrCreate(
            ['client_id' => $client->id, 'type' => 'Percentage Tax', 'period' => now()->subMonth()->format('Y-m')],
            [
                'due_date' => now()->subDays(2),
                'filed_at' => now()->subDays(5),
                'status' => Filing::STATUS_FILED,
            ]
        );

        $announcements = [
            [
                'title' => 'BIR deadline reminder — September',
                'body' => 'Percentage tax returns are due on the 20th of this month. Make sure your sales figures are submitted on time so we can file your 2551Q return.',
                'posted_at' => now()->subDays(2),
            ],
            [
                'title' => 'New client portal is live',
                'body' => 'You can now view your billing statements, submit your quarterly sales, and follow your filings right from your phone.',
                'posted_at' => now()->subWeeks(1),
            ],
            [
                'title' => 'Filing deadline reminder — BIR returns',
                'body' => 'BIR returns are due on the 15th of the month. Stay on top of your filings with Egliane — upload your documents early so we can review them before the deadline.',
                'posted_at' => now()->subWeeks(2),
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::query()->firstOrCreate(
                ['body' => $announcement['body']],
                array_merge($announcement, ['posted_by' => $admin->id])
            );
        }

        Setting::set('chatbot_enabled', '1');
        Setting::set('tax_2551q_rate', '3');
        Setting::set('fee_2551q', '500');
        Setting::set('fee_1701q', '800');
        Setting::set('fee_bookkeeping', '1500');

        $billingSeeds = [
            [
                'period_label' => '1ST QUARTER 2026 BILLING',
                'quarter' => 1,
                'year' => 2026,
                'due_date' => now()->subMonths(2)->startOfMonth()->addDays(12)->toDateString(),
                'sales' => 84250.00,
                'tax_2551q' => 2527.50,
                'fee_2551q' => 500.00,
                'fee_1701q' => 800.00,
                'fee_bookkeeping' => 1500.00,
                'total' => 5327.50,
                'status' => Billing::STATUS_PAID,
                'paid_at' => now()->subMonths(3)->startOfMonth()->addDays(4),
                'sales_submitted_at' => now()->subMonths(3)->startOfMonth()->addDays(2),
            ],
            [
                'period_label' => '2ND QUARTER 2026 BILLING',
                'quarter' => 2,
                'year' => 2026,
                'due_date' => now()->addDays(5)->toDateString(),
                'sales' => 92400.00,
                'tax_2551q' => 2772.00,
                'fee_2551q' => 500.00,
                'fee_1701q' => 800.00,
                'fee_bookkeeping' => 1500.00,
                'total' => 5572.00,
                'status' => Billing::STATUS_UNPAID,
                'paid_at' => null,
                'sales_submitted_at' => now()->subMonth()->startOfMonth()->addDays(3),
            ],
            [
                'period_label' => '3RD QUARTER 2026 BILLING',
                'quarter' => 3,
                'year' => 2026,
                'due_date' => now()->subDays(3)->toDateString(),
                'sales' => 88600.00,
                'tax_2551q' => 2658.00,
                'fee_2551q' => 500.00,
                'fee_1701q' => 800.00,
                'fee_bookkeeping' => 1500.00,
                'total' => 5458.00,
                'status' => Billing::STATUS_OVERDUE,
                'paid_at' => null,
                'sales_submitted_at' => null,
            ],
        ];

        foreach ($billingSeeds as $seed) {
            Billing::query()->firstOrCreate(
                ['client_id' => $client->id, 'period_label' => $seed['period_label']],
                array_merge($seed, ['created_by' => $admin->id, 'updated_by' => $admin->id])
            );
        }

        $birSeeds = [
            'EFPS' => BirFormStatus::STATUS_FILED,
            '2551Q' => BirFormStatus::STATUS_FILED,
            '1701' => BirFormStatus::STATUS_NOT_FILED,
            '1701Q' => BirFormStatus::STATUS_NOT_FILED,
            '2550Q' => BirFormStatus::STATUS_FILED,
            '1601C' => BirFormStatus::STATUS_NOT_APPLICABLE,
            '1601EQ' => BirFormStatus::STATUS_NOT_APPLICABLE,
            '0619E' => BirFormStatus::STATUS_NOT_FILED,
            '2550M' => BirFormStatus::STATUS_NOT_FILED,
            '0619F' => BirFormStatus::STATUS_NOT_APPLICABLE,
            '1601FQ' => BirFormStatus::STATUS_NOT_APPLICABLE,
            '1702Q' => BirFormStatus::STATUS_NOT_FILED,
        ];

        foreach ($birSeeds as $formType => $status) {
            BirFormStatus::query()->firstOrCreate(
                ['client_id' => $client->id, 'form_type' => $formType],
                ['status' => $status, 'updated_by' => $admin->id]
            );
        }

        ActivityLog::record($admin, 'seed.run', 'Database seeded with demo accounts.');
    }
}
