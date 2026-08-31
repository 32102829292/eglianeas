<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Billing;
use App\Models\ClientSurveyResponse;
use App\Models\CompanyCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientFlowQaTest extends TestCase
{
    use RefreshDatabase;

    private static int $quarterCounter = 0;

    private function client(string $label = 'QA Client'): User
    {
        $user = User::create([
            'name' => $label,
            'email' => 'qa'.uniqid().'@gmail.com',
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

    private function billing(User $client, string $status, float $total): Billing
    {
        self::$quarterCounter++;
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = (self::$quarterCounter % 4) + 1;
        $billing->year = 2026 + intdiv(self::$quarterCounter, 4);
        $billing->period_label = '1ST QUARTER 2026 BILLING';
        $billing->cash_in = 0;
        $billing->total = $total;
        $billing->status = $status;
        $billing->due_date = now()->addDays(5)->toDateString();
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        return $billing;
    }

    private function validSignupPayload(string $email): array
    {
        return [
            'name' => 'New Client',
            'business_name' => 'New Biz Co',
            'email' => $email,
            'pin' => '1234',
            'pin_confirmation' => '1234',
            'terms' => '1',
            'business_type' => 'Sole Proprietorship',
            'line_of_business' => 'Retail & Wholesale',
            'bir_registration_type' => 'VAT',
            'business_address' => '123 Rizal Ave, Quezon City',
            'contact_no' => '09171234567',
            'second_contact_name' => 'Second Person',
            'second_contact_channel' => 'phone',
            'second_contact_no' => '09181234567',
            'second_email' => 'second'.uniqid().'@gmail.com',
            'birth_date' => '1990-01-01',
            'tin_no' => '123-456-789',
            'mother_maiden_name' => 'Mama',
            'father_name' => 'Papa',
        ];
    }

    public function test_login_page_has_pin_and_face_tabs_and_no_dead_forgot_password(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('id="keypad"', false);
        $response->assertSee('data-tab="pin"', false);
        $response->assertSee('data-tab="face"', false);
        $response->assertSee('id="savedAccounts"', false);
        $response->assertSee('/forgot-pin');
        $response->assertDontSee('/forgot-password');
    }

    public function test_home_and_about_public_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/about')->assertOk()->assertSee('Egliane');
    }

    public function test_about_lightbox_present_with_certificates_and_absent_without(): void
    {
        $this->get('/about')->assertOk()->assertDontSee('id="certLightbox"');

        CompanyCertificate::create([
            'label' => 'SEC Registration',
            'file_path' => 'certificates/sec.png',
            'original_name' => 'sec.png',
            'mime_type' => 'image/png',
            'size' => 1000,
            'uploaded_at' => now(),
        ]);

        $response = $this->get('/about');
        $response->assertOk();
        $response->assertSee('id="certLightbox"', false);
        $response->assertSee('cert-lightbox-trigger', false);
    }

    public function test_rejects_duplicate_verified_email_on_signup(): void
    {
        Mail::fake();

        $email = 'collision'.uniqid().'@gmail.com';
        User::create([
            'name' => 'Holder',
            'email' => $email,
            'password' => bcrypt('x'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);

        $this->post('/register', $this->validSignupPayload($email))
            ->assertRedirect();
        $this->assertSame(1, User::where('email', $email)->count());
    }

    public function test_signup_rejects_non_gmail(): void
    {
        $this->post('/register', [
            'name' => 'No Gmail',
            'business_name' => 'Test Co',
            'email' => 'someone@yahoo.com',
            'pin' => '1234',
            'pin_confirmation' => '1234',
            'terms' => '1',
        ])->assertSessionHasErrors('email');
    }

    public function test_full_signup_flow_verifies_and_reaches_client_dashboard(): void
    {
        Mail::fake();

        $payload = $this->validSignupPayload('newclient'.uniqid().'@gmail.com');

        $this->post('/register', $payload)
            ->assertRedirect(route('verify.account'));

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->pin_set_at);
        $this->assertNotNull($user->getClientProfile()->business_address);
        $this->assertNull($user->email_verified_at);

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->assertNotNull($code);

        $this->post('/verify-account', ['code' => $code])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_client_dashboard_and_core_pages_render(): void
    {
        $user = $this->client();

        $this->actingAs($user)->get('/client/dashboard')->assertOk();
        $this->actingAs($user)->get('/client/billing')->assertOk();
        $this->actingAs($user)->get('/client/collections')->assertOk();
        $this->actingAs($user)->get('/client/other-services')->assertOk();
        $this->actingAs($user)->get('/client/other-services/collections')->assertOk();
        $this->actingAs($user)->get('/client/service-tracker')->assertOk();
        $this->actingAs($user)->get('/client/service-tracker/concerns')->assertOk();
        $this->actingAs($user)->get('/client/documents')->assertOk();
    }

    public function test_client_profile_edit_page_loads_leaflet_assets(): void
    {
        $user = $this->client();

        $response = $this->actingAs($user)->get('/client/profile');

        $response->assertOk();
        $response->assertSee('id="profileMapLoader"', false);
        $response->assertSee('vendor/leaflet/leaflet.css');
        $response->assertSee('vendor/leaflet/leaflet.js');
        $response->assertSee('/js/address-map.js');
        $response->assertSee('id="locateAddressBtn"', false);
        $response->assertSee('id="latitude"', false);
        $response->assertSee('id="longitude"', false);
    }

    public function test_paid_billing_receipt_shows_paid_stamp_and_date(): void
    {
        $user = $this->client('Receipt Client');
        $billing = $this->billing($user, Billing::STATUS_PAID, 500.00);
        $billing->paid_at = now()->subDays(2);
        $billing->save();

        $response = $this->actingAs($user)->get("/client/billing/{$billing->id}");

        $response->assertOk();
        $response->assertSee('1ST QUARTER 2026 BILLING');
        $response->assertSee('paid-stamp');
        $response->assertSee('Date paid');
    }

    public function test_unpaid_billing_receipt_has_no_paid_stamp(): void
    {
        $user = $this->client('Unpaid Receipt Client');
        $billing = $this->billing($user, Billing::STATUS_UNPAID, 700.00);

        $response = $this->actingAs($user)->get("/client/billing/{$billing->id}");

        $response->assertOk()->assertDontSee('paid-stamp');
    }

    public function test_collections_summary_math(): void
    {
        $user = $this->client('Math Client');

        $this->billing($user, Billing::STATUS_PAID, 500.00);
        $this->billing($user, Billing::STATUS_PAID, 300.00);
        $this->billing($user, Billing::STATUS_UNPAID, 200.00);

        $response = $this->actingAs($user)->get('/client/collections');

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return abs($summary['total'] - 1000.0) < 0.001
                && abs($summary['paid'] - 800.0) < 0.001
                && abs($summary['outstanding'] - 200.0) < 0.001;
        });
    }

    public function test_client_geocode_sends_ua_and_countrycodes(): void
    {
        $user = $this->client();

        config(['geocoding.api_key' => 'test-locationiq-key']);

        Http::fake([
            'us1.locationiq.com/v1/search*' => Http::response([
                ['lat' => '10.31', 'lon' => '123.88', 'display_name' => 'Cebu City, Philippines'],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post('/client/geocode', [
            'q' => 'Cebu City',
        ]);

        $response->assertOk()->assertJsonPath('lat', 10.31);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'us1.locationiq.com/v1/search')
                && $request['countrycodes'] === 'ph'
                && $request['key'] === 'test-locationiq-key';
        });
        Http::assertSent(function ($request) {
            return $request->hasHeader('User-Agent', 'EglianeAccountingServices/1.0 (contact: support@eglianeas.com; https://eglianeas.com)');
        });
    }
}
