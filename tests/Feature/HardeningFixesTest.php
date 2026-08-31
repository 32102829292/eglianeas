<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\User;
use App\Services\BillingReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardeningFixesTest extends TestCase
{
    use RefreshDatabase;

    private static int $quarterCounter = 0;

    private function internal(string $role, string $label): User
    {
        return User::create([
            'name' => $label,
            'email' => strtolower($role).uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $label = 'QA Client'): User
    {
        return User::create([
            'name' => $label,
            'email' => 'qa'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    private function billing(User $client, string $status): Billing
    {
        self::$quarterCounter++;
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = (self::$quarterCounter % 4) + 1;
        $billing->year = 2026 + intdiv(self::$quarterCounter, 4);
        $billing->period_label = '1ST QUARTER 2026 BILLING';
        $billing->cash_in = 0;
        $billing->status = $status;
        $billing->due_date = now()->addDays(3)->toDateString();
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        BillingLineItem::create([
            'billing_id' => $billing->id,
            'category' => BillingLineItem::CATEGORY_BIR_REMITTANCE,
            'form_type' => '1601C',
            'label' => '1601C',
            'month' => 1,
            'amount' => 250.00,
            'fee_rate_id' => null,
        ]);

        $billing->recomputeTotal();
        $billing->save();

        return $billing;
    }

    public function test_dashboard_donut_includes_unpaid_segment(): void
    {
        $admin = $this->internal(User::ROLE_ADMIN, 'Admin');
        $client = $this->client('Donut Client');

        $this->billing($client, Billing::STATUS_PAID);
        $this->billing($client, Billing::STATUS_PAID);
        $this->billing($client, Billing::STATUS_PAID);
        $this->billing($client, Billing::STATUS_UNPAID);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

$response->assertOk();
        // The SVG donut must count all four billings (Paid + Unpaid, not just the paid ones).
        $response->assertSee('donut-chart-total">4<', false);
        // The Unpaid segment uses the navy token as its color — proves it is no longer omitted.
        $response->assertSee('var(--navy)', false);
        $response->assertDontSee('No billing data yet.');
    }

    public function test_profile_save_clears_stale_coordinates_when_geocode_fails(): void
    {
        $user = $this->client('Stale Coords Client');
        $profile = $user->getClientProfile();
        $profile->business_address = '123 Rizal Avenue, Quezon City';
        $profile->latitude = 14.5995;
        $profile->longitude = 121.0419;
        $profile->save();

        Http::fake([
            'us1.locationiq.com/v1/search*' => Http::response('[]', 200),
        ]);

        $this->actingAs($user)->patch('/client/profile', [
            'business_address' => '456 Unknown Street, Somewhere',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $profile->refresh();
        $this->assertSame('456 Unknown Street, Somewhere', $profile->business_address);
        $this->assertNull($profile->latitude);
        $this->assertNull($profile->longitude);
    }

    public function test_profile_save_writes_geocoded_coordinates_on_address_change(): void
    {
        $user = $this->client('Geocode Client');
        $user->getClientProfile();

        Http::fake([
            'us1.locationiq.com/v1/search*' => Http::response([
                [
                    'lat' => '10.3157',
                    'lon' => '123.8854',
                    'display_name' => 'Cebu City, Philippines',
                ],
            ], 200),
        ]);

        $this->actingAs($user)->patch('/client/profile', [
            'business_address' => 'Cebu City',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $profile = $user->getClientProfile()->refresh();
        $this->assertSame('Cebu City', $profile->business_address);
        $this->assertSame(10.3157, (float) $profile->latitude);
        $this->assertSame(123.8854, (float) $profile->longitude);
    }

    public function test_reminders_are_throttled_by_cooldown(): void
    {
        $client = $this->client('Reminder Client');
        $billing = $this->billing($client, Billing::STATUS_UNPAID);
        $billing->due_date = now()->subDay()->toDateString();

        // Already reminded once 8 days ago -> outside the 7-day cooldown.
        $billing->reminder_sent_at = now()->subDays(8);
        $billing->save();

        $this->assertSame(1, BillingReminderService::remindBillsDue());

        // Immediately re-running must not send again (cooldown window).
        $this->assertSame(0, BillingReminderService::remindBillsDue());
        $this->assertSame(0, BillingReminderService::remindBillsDue());

        $billing->refresh();
        $this->assertSame(Billing::STATUS_OVERDUE, $billing->status);
        $this->assertNotNull($billing->reminder_sent_at);
    }

    public function test_fresh_unpaid_bill_sends_once_and_updates_stamp(): void
    {
        $client = $this->client('Fresh Reminder Client');
        $billing = $this->billing($client, Billing::STATUS_UNPAID);
        $billing->due_date = now()->addDays(2)->toDateString();
        $billing->save();

        $this->assertSame(1, BillingReminderService::remindBillsDue());
        $this->assertSame(0, BillingReminderService::remindBillsDue());

        $billing->refresh();
        $this->assertNotNull($billing->reminder_sent_at);
    }
}