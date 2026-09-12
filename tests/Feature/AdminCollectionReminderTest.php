<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Mail\BillingStatementMail;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminCollectionReminderTest extends TestCase
{
    use RefreshDatabase;

    private function staffOrAdmin(string $role): User
    {
        return User::create([
            'name' => $role.' Reminder QA',
            'email' => $role.'-reminder-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(?string $email = null): User
    {
        return User::create([
            'name' => 'Reminder QA Client',
            'email' => $email ?? 'reminder-client-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    private function billing(User $client, string $status, bool $overdue = false): Billing
    {
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = 1;
        $billing->year = 2026;
        $billing->period_label = 'Q1 QUARTER 2026 BILLING';
        $billing->cash_in = 0;
        $billing->total = 1234.56;
        $billing->status = $status;
        $billing->due_date = $overdue
            ? date('Y-m-d', strtotime('-10 days'))
            : date('Y-m-d', strtotime('+10 days'));
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        return $billing;
    }

    public function test_admin_can_send_reminder_email_and_in_app_notification_for_unpaid_billing(): void
    {
        Mail::fake();

        $admin = $this->staffOrAdmin(User::ROLE_ADMIN);
        $client = $this->client();
        $billing = $this->billing($client, Billing::STATUS_UNPAID);

        $origin = route('admin.collections.index');

        $this->actingAs($admin)
            ->from($origin)
            ->post(route('admin.collections.remind', $billing))
            ->assertRedirect($origin)
            ->assertSessionHas('status', 'Payment reminder sent to the client.');

        Mail::assertSent(
            BillingStatementMail::class,
            fn (BillingStatementMail $mail) => $mail->hasTo($client->email)
        );

        Mail::assertSent(BillingStatementMail::class, 1);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'group_key' => "billing_due:{$billing->id}",
            'type' => 'billing_due',
            'reminder_count' => 1,
        ]);
        $this->assertNull(Notification::where('group_key', "billing_due:{$billing->id}")->first()->read_at);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.collection_reminded',
            'user_id' => $admin->id,
        ]);
    }

    public function test_overdue_billing_reminder_uses_overdue_channels(): void
    {
        Mail::fake();

        $admin = $this->staffOrAdmin(User::ROLE_ADMIN);
        $client = $this->client();
        $billing = $this->billing($client, Billing::STATUS_OVERDUE, overdue: true);

        $this->actingAs($admin)->post(route('admin.collections.remind', $billing));

        Mail::assertSent(BillingStatementMail::class, 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'group_key' => "billing_due:{$billing->id}",
            'type' => 'billing_overdue',
        ]);
    }

    public function test_staff_can_send_reminder(): void
    {
        Mail::fake();

        $staff = $this->staffOrAdmin(User::ROLE_STAFF);
        $client = $this->client();
        $billing = $this->billing($client, Billing::STATUS_UNPAID);

        $this->actingAs($staff)
            ->from(route('admin.collections.index'))
            ->post(route('admin.collections.remind', $billing))
            ->assertRedirect(route('admin.collections.index'))
            ->assertSessionHas('status');

        Mail::assertSent(BillingStatementMail::class, 1);
        $this->assertDatabaseHas('notifications', ['group_key' => "billing_due:{$billing->id}"]);
    }

    public function test_client_cannot_remind_another_billing(): void
    {
        Mail::fake();

        $intruder = $this->client();
        $client = $this->client();
        $billing = $this->billing($client, Billing::STATUS_UNPAID);

        $this->actingAs($intruder)
            ->post(route('admin.collections.remind', $billing))
            ->assertForbidden();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_paid_billing_reminder_is_blocked_gracefully(): void
    {
        Mail::fake();

        $admin = $this->staffOrAdmin(User::ROLE_ADMIN);
        $client = $this->client();
        $billing = $this->billing($client, Billing::STATUS_PAID);

        $this->actingAs($admin)
            ->from(route('admin.collections.index'))
            ->post(route('admin.collections.remind', $billing))
            ->assertRedirect(route('admin.collections.index'))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_client_without_email_reminder_is_blocked_gracefully(): void
    {
        Mail::fake();

        $admin = $this->staffOrAdmin(User::ROLE_ADMIN);
        $client = User::create([
            'name' => 'No Email Client',
            'email' => '',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
        $billing = $this->billing($client, Billing::STATUS_UNPAID);

        $this->actingAs($admin)
            ->from(route('admin.collections.index'))
            ->post(route('admin.collections.remind', $billing))
            ->assertRedirect(route('admin.collections.index'))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_billing_without_client_reminder_is_blocked_gracefully(): void
    {
        Mail::fake();

        $admin = $this->staffOrAdmin(User::ROLE_ADMIN);
        $client = $this->client();
        $billing = $this->billing($client, Billing::STATUS_UNPAID);
        $client->delete();

        $this->actingAs($admin)
            ->from(route('admin.collections.index'))
            ->post(route('admin.collections.remind', $billing))
            ->assertRedirect(route('admin.collections.index'))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_reminding_twice_collapses_into_one_notification_with_incremented_count(): void
    {
        Mail::fake();

        $admin = $this->staffOrAdmin(User::ROLE_ADMIN);
        $client = $this->client();
        $billing = $this->billing($client, Billing::STATUS_UNPAID);

        $this->actingAs($admin)->post(route('admin.collections.remind', $billing));
        $this->actingAs($admin)->post(route('admin.collections.remind', $billing));

        Mail::assertSent(BillingStatementMail::class, 2);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'group_key' => "billing_due:{$billing->id}",
            'reminder_count' => 2,
        ]);
    }
}