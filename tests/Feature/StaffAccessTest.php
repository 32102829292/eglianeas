<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Mail\VerificationCodeMail;
use App\Models\Billing;
use App\Models\ClientProfile;
use App\Models\ClientSurveyResponse;
use App\Models\Document;
use App\Models\OtherService;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

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

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'Staff Member');
    }

    private function admin(): User
    {
        return $this->internal(User::ROLE_ADMIN, 'Admin Member');
    }

    private function client(): User
    {
        $user = User::create([
            'name' => 'Test Client',
            'email' => 'client'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);

        ClientSurveyResponse::create([
            'user_id' => $user->id,
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => null,
            'submitted_at' => now(),
        ]);

        return $user;
    }

    private function unpaidBilling(User $client): Billing
    {
        return Billing::create([
            'client_id' => $client->id,
            'period_label' => '1ST QUARTER 2026 BILLING',
            'quarter' => 1,
            'year' => 2026,
            'due_date' => now()->addDays(5)->toDateString(),
            'cash_in' => 0,
            'total' => 5327.50,
            'status' => Billing::STATUS_UNPAID,
            'created_by' => $client->id,
            'updated_by' => $client->id,
        ]);
    }

    public function test_staff_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_staff_can_access_admin_collections_page(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.collections.index'))
            ->assertOk();
    }

    public function test_client_is_blocked_from_admin_routes(): void
    {
        $this->actingAs($this->client())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_staff_pay_returns_403_and_does_not_mark_paid(): void
    {
        $staff = $this->staff();
        $billing = $this->unpaidBilling($this->client());

        $this->actingAs($staff)
            ->post(route('admin.billing.pay', $billing), [
                'status' => Billing::STATUS_PAID,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertForbidden();

        $this->assertSame(Billing::STATUS_UNPAID, $billing->fresh()->status);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'admin.billing_paid',
            'user_id' => $staff->id,
        ]);
    }

    public function test_admin_can_mark_paid_and_activity_is_logged(): void
    {
        $admin = $this->admin();
        $billing = $this->unpaidBilling($this->client());

        $this->actingAs($admin)
            ->post(route('admin.billing.pay', $billing), [
                'status' => Billing::STATUS_PAID,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertSame(Billing::STATUS_PAID, $billing->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.billing_paid',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.billing_draft_created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_staff_activity_logging_captures_collection_reminder(): void
    {
        $staff = $this->staff();
        $billing = $this->unpaidBilling($this->client());

        $this->actingAs($staff)
            ->post(route('admin.collections.remind', $billing))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.collection_reminded',
            'user_id' => $staff->id,
        ]);
    }

    public function test_collections_page_hides_mark_paid_for_staff(): void
    {
        $this->unpaidBilling($this->client());

        $this->actingAs($this->staff())
            ->get(route('admin.collections.index'))
            ->assertOk()
            ->assertSee('Send reminder')
            ->assertDontSee('Mark paid');

        $this->actingAs($this->admin())
            ->get(route('admin.collections.index'))
            ->assertOk()
            ->assertSee('Mark paid');
    }

    public function test_staff_can_access_user_management(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.users.index'))
            ->assertOk();

        $staff = $this->staff();
        $this->actingAs($staff)
            ->post(route('admin.users.store'), [
                'name' => 'Another Staff',
                'email' => 'another'.uniqid().'@example.com',
                'role' => User::ROLE_STAFF,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.user_created',
            'user_id' => $staff->id,
        ]);
    }

    public function test_admin_can_create_staff_account(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Staff',
                'email' => 'new.staff@example.com',
                'role' => User::ROLE_STAFF,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.staff@example.com',
            'role' => User::ROLE_STAFF,
        ]);

        $user = User::query()->where('email', 'new.staff@example.com')->firstOrFail();

        $this->assertNull($user->password);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.user_created',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'account',
        ]);
        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $user->id,
        ]);
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_created_account_can_set_pin_via_forgot_pin_flow(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $email = 'pinless'.uniqid().'@example.com';

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'PINless Staff',
                'email' => $email,
                'role' => User::ROLE_STAFF,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertNull($user->password);

        $sentCode = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$sentCode) {
            $sentCode = $mail->code;

            return true;
        });
        $this->assertNotNull($sentCode);
        $this->assertEquals(1, VerificationCode::query()->where('user_id', $user->id)->count());

        Auth::logout();

        $this->get(route('forgot-pin.verify'))->assertOk();

        $this->post(route('forgot-pin.verify.post'), [
            'email' => $email,
            'code' => $sentCode,
        ])->assertRedirect(route('forgot-pin.reset'));

        $this->post(route('forgot-pin.reset.post'), [
            'pin' => '2468',
            'pin_confirmation' => '2468',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('2468', $user->fresh()->pin));

        $this->post(route('login.pin'), [
            'email' => $email,
            'pin' => '2468',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_delete_team_account(): void
    {
        $admin = $this->admin();
        $target = $this->internal(User::ROLE_STAFF, 'To Be Deleted');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.user_deleted',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_staff_cannot_delete_team_accounts(): void
    {
        $staff = $this->staff();
        $target = $this->internal(User::ROLE_ADMIN, 'Protected Admin');

        $this->actingAs($staff)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_staff_can_access_operational_areas(): void
    {
        $client = $this->client();

        $urls = [
            route('admin.billing.create'),
            route('admin.billing.index'),
            route('admin.clients.index'),
            route('admin.clients.show', $client),
            route('admin.clients.edit', $client),
            route('admin.distribution.index'),
            route('admin.bir-forms.index'),
            route('admin.service-tracker.index'),
            route('admin.service-tracker.concerns'),
            route('admin.other-services.fill-up'),
            route('admin.other-services.collections'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->staff())->get($url)->assertOk();
        }
    }

    public function test_staff_can_create_billing_statements(): void
    {
        $client = $this->client();

        $this->actingAs($this->staff())
            ->post(route('admin.billing.store'), [
                'client_id' => $client->id,
                'quarter' => 2,
                'year' => 2026,
                'due_date' => now()->addDays(10)->toDateString(),
                'cash_in' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('billings', [
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'status' => Billing::STATUS_UNPAID,
        ]);
    }

    public function test_staff_can_access_announcement_management(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.announcements.index'))
            ->assertOk();

        $this->actingAs($this->staff())
            ->post(route('admin.announcements.store'), ['body' => 'Quarterly update'])
            ->assertRedirect();
    }

    public function test_staff_can_access_about_and_chatbot_admin_pages(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.about'))
            ->assertOk();

        $this->actingAs($this->staff())
            ->get(route('admin.chatbot'))
            ->assertOk();
    }

    public function test_staff_can_access_billing_and_other_services_settings(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.billing.settings'))
            ->assertOk();

        $this->actingAs($this->staff())
            ->get(route('admin.billing.paymentSettings'))
            ->assertOk();

        $this->actingAs($this->staff())
            ->get(route('admin.other-services.settings'))
            ->assertOk();
    }

    public function test_staff_can_create_fee_rates_and_service_types(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.billing.feeRates.store'), ['amount' => 100, 'category' => 'bookkeeping_fee'])
            ->assertRedirect();

        $this->assertDatabaseHas('fee_rates', ['amount' => 100]);

        $this->actingAs($staff)
            ->post(route('admin.other-services.service-types.store'), ['label' => 'Secretarial'])
            ->assertRedirect();

        $this->assertDatabaseHas('service_types', ['label' => 'Secretarial']);
    }

    public function test_admin_can_access_configuration_pages(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.announcements.index'))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.about'))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.chatbot'))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.billing.settings'))
            ->assertOk();
    }

    public function test_staff_can_delete_client_accounts(): void
    {
        $client = $this->client();

        $this->actingAs($this->staff())
            ->delete(route('admin.clients.destroy', $client))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $client->id]);
    }

    public function test_admin_can_delete_client_accounts(): void
    {
        $client = $this->client();

        $this->actingAs($this->admin())
            ->delete(route('admin.clients.destroy', $client))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $client->id]);
    }

    public function test_staff_other_service_pay_returns_403_and_does_not_mark_paid(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $service = OtherService::create([
            'client_id' => $client->id,
            'custom_label' => 'Notarization',
            'amount' => 500.00,
            'status' => OtherService::STATUS_UNPAID,
            'requested_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('admin.other-services.pay', $service), [
                'status' => OtherService::STATUS_PAID,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertForbidden();

        $this->assertSame(OtherService::STATUS_UNPAID, $service->fresh()->status);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'admin.other_service_paid',
            'user_id' => $staff->id,
        ]);
    }

    public function test_admin_can_mark_other_service_paid(): void
    {
        $client = $this->client();
        $service = OtherService::create([
            'client_id' => $client->id,
            'custom_label' => 'Notarization',
            'amount' => 700.00,
            'status' => OtherService::STATUS_UNPAID,
            'requested_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.other-services.pay', $service), [
                'status' => OtherService::STATUS_PAID,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertSame(OtherService::STATUS_PAID, $service->fresh()->status);
    }

    public function test_other_services_collections_hides_mark_paid_for_staff(): void
    {
        $client = $this->client();
        OtherService::create([
            'client_id' => $client->id,
            'custom_label' => 'Audit support',
            'amount' => 1200.00,
            'status' => OtherService::STATUS_UNPAID,
            'requested_at' => now(),
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.other-services.collections'))
            ->assertOk()
            ->assertDontSee('Mark paid');

        $this->actingAs($this->admin())
            ->get(route('admin.other-services.collections'))
            ->assertOk()
            ->assertSee('Mark paid');
    }

    public function test_staff_can_set_client_lifecycle_state(): void
    {
        $client = $this->client();
        $client->getClientProfile()->update([
            'status' => ClientProfile::STATUS_CURRENT,
            'payment_status' => 'unpaid',
            'date_started' => '2024-01-15',
            'remarks' => 'owner-verified',
        ]);

        $this->actingAs($this->staff())
            ->put(route('admin.clients.update', $client), [
                'name' => 'Updated Name',
                'email' => $client->email,
                'business_name' => 'Updated Biz',
                'status' => ClientProfile::STATUS_PENDING,
                'payment_status' => 'paid',
                'date_started' => '2026-08-01',
                'remarks' => 'staff updated remarks',
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertSame('Updated Name', $client->fresh()->name);
        $this->assertSame('Updated Biz', $client->fresh()->business_name);

        $profile = $client->fresh()->profile;
        $this->assertSame(ClientProfile::STATUS_PENDING, $profile->status);
        $this->assertSame('paid', $profile->payment_status);
        $this->assertSame('2026-08-01', $profile->date_started->format('Y-m-d'));
        $this->assertSame('staff updated remarks', $profile->remarks);
    }

    public function test_admin_can_set_client_lifecycle_state(): void
    {
        $client = $this->client();

        $this->actingAs($this->admin())
            ->put(route('admin.clients.update', $client), [
                'name' => $client->name,
                'email' => $client->email,
                'status' => ClientProfile::STATUS_CURRENT,
                'payment_status' => 'paid',
                'date_started' => '2026-01-10',
                'remarks' => 'welcome on board',
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $profile = $client->fresh()->profile;
        $this->assertSame(ClientProfile::STATUS_CURRENT, $profile->status);
        $this->assertSame('paid', $profile->payment_status);
        $this->assertSame('2026-01-10', $profile->date_started->format('Y-m-d'));
        $this->assertSame('welcome on board', $profile->remarks);
    }

    public function test_staff_can_view_client_documents_but_clients_cannot_cross_view(): void
    {
        $staff = $this->staff();
        $owner = $this->client();
        Storage::fake('supabase');

        $document = Document::create([
            'user_id' => $owner->id,
            'client_id' => $owner->id,
            'name' => 'COR.pdf',
            'original_name' => 'COR.pdf',
            'path' => "cor/{$owner->id}/cor.pdf",
            'mime_type' => 'application/pdf',
            'size' => 1234,
            'form_type' => 'COR',
        ]);
        Storage::disk('supabase')->put($document->path, 'PDFBODY');

        $this->actingAs($staff)
            ->get(route('documents.view', $document))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('documents.file', $document))
            ->assertRedirect();

        $this->assertDatabaseHas('cor_view_logs', [
            'document_id' => $document->id,
            'viewed_by' => $staff->id,
        ]);

        $otherClient = $this->client();
        $this->actingAs($otherClient)
            ->get(route('documents.view', $document))
            ->assertForbidden();
    }

    public function test_client_concern_notifies_staff_and_admin(): void
    {
        $staff = $this->staff();
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($client)
            ->post(route('client.service-tracker.concerns.store'), [
                'description_of_issue' => 'Missing receipt for Q1.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type' => 'client_concern',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'client_concern',
        ]);
    }

    public function test_staff_can_impersonate_clients(): void
    {
        $staff = $this->staff();
        $client = $this->client();

        $this->actingAs($staff)
            ->post(route('admin.clients.impersonate', $client))
            ->assertRedirect(route('client.dashboard'));

        $this->assertSame($staff->id, session('impersonator_id'));
    }

    public function test_staff_must_acknowledge_confidentiality_before_admin_area(): void
    {
        $staff = User::create([
            'name' => 'Fresh Staff',
            'email' => 'fresh.staff'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.confidentiality.acknowledge'));

        $this->actingAs($staff)
            ->post(route('admin.confidentiality.acknowledge.store'), ['agree' => '1'])
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_staff_cannot_access_activity_logs(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.activity-logs'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.activity-logs'))
            ->assertOk();
    }

    public function test_staff_nav_hides_only_activity_logs(): void
    {
        $this->unpaidBilling($this->client());

        $this->actingAs($this->staff())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee('Team Accounts')
            ->assertDontSee('Activity Logs');

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee('Team Accounts')
            ->assertSee('Activity Logs');
    }
}
