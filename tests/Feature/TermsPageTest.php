<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TermsPageTest extends TestCase
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

    private function admin(): User
    {
        return $this->internal(User::ROLE_ADMIN, 'Terms Admin');
    }

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'Terms Staff');
    }

    private function signaturePng(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    private function printAreaHtml(\Illuminate\Testing\TestResponse $response): string
    {
        $html = $response->getContent();
        $needle = '<div class="dpp-print">';
        $start = strpos($html, $needle);
        if ($start === false) {
            return '';
        }
        $open = strpos($html, '>', $start) + 1;
        $depth = 0;
        $length = strlen($html);
        for ($i = $open; $i < $length; $i++) {
            if (substr($html, $i, 4) === '<div') {
                $depth++;
                $i += 3;
                continue;
            }
            if (substr($html, $i, 6) === '</div>') {
                if ($depth === 0) {
                    return substr($html, $open, $i - $open);
                }
                $depth--;
                $i += 5;
            }
        }

        return '';
    }

    public function test_terms_page_is_public_and_lists_ack_content(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('Terms &amp; Confidentiality', false)
            ->assertSee('Print Data Privacy Policy')
            ->assertSee('Terms of Use')
            ->assertSee('Data Privacy Act of 2012 (Republic Act No. 10173)')
            ->assertSee('Data Privacy Acknowledgement')
            ->assertSee('Log in to your account');
    }

    public function test_terms_print_area_contains_only_policy_content(): void
    {
        Storage::fake('supabase');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $this->signaturePng(),
            ])
            ->assertRedirect(route('terms'));

        $print = $this->printAreaHtml($this->actingAs($admin)->get(route('terms')));

        $this->assertStringContainsString('Egliane Accounting Services', $print);
        $this->assertStringContainsString('Confidentiality &amp; Data Privacy Policy', $print);
        $this->assertStringContainsString('Data Privacy Act of 2012', $print);
        $this->assertStringContainsString('Republic Act No. 10173', $print);
        $this->assertStringContainsString('Policy Version '.EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION, $print);
        $this->assertStringContainsString('ACKNOWLEDGEMENT', $print);
        $this->assertStringContainsString('I acknowledge that I have read and understood the Data Privacy and Confidentiality Policy of Egliane Accounting Services and agree to comply with its requirements.', $print);
        $this->assertStringContainsString('core principles include', $print);
        $this->assertStringContainsString('All client information, financial data', $print);

        $this->assertStringNotContainsString('Terms of Use', $print);
        $this->assertStringNotContainsString('Print Data Privacy Policy', $print);
        $this->assertStringNotContainsString('sigPad', $print);
        $this->assertStringNotContainsString('signature_data', $print);
        $this->assertStringNotContainsString('termsAckForm', $print);
        $this->assertStringNotContainsString('Please provide', $print);
        $this->assertStringNotContainsString('Account ID', $print);
        $this->assertStringNotContainsString('@example.com', $print);
        $this->assertStringNotContainsString('Signed', $print);
        $this->assertStringNotContainsString('Log in to your account', $print);
    }

    public function test_terms_print_area_shows_actual_signature_and_signer_details_for_signed_user(): void
    {
        Storage::fake('supabase');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $this->signaturePng(),
            ])
            ->assertRedirect(route('terms'));

        $signature = Signature::where('user_id', $admin->id)->first();
        $this->assertNotNull($signature);

        $print = $this->printAreaHtml($this->actingAs($admin)->get(route('terms')));

        $this->assertStringContainsString(route('confidentiality.signature.image', $signature), $print);
        $this->assertStringContainsString('<span class="dpp-sig-v">Terms Admin</span>', $print);
        $this->assertStringContainsString('<span class="dpp-sig-v is-plain">Admin</span>', $print);
        $this->assertStringContainsString('<span class="dpp-sig-v">'.now()->format('F j, Y').'</span>', $print);

        $this->assertStringNotContainsString($admin->email, $print);
        $this->assertStringNotContainsString('Account ID', $print);
        $this->assertStringNotContainsString('#'.Signature::where('user_id', $admin->id)->value('id'), $print);
    }

    public function test_terms_print_area_is_blank_for_unsigned_user(): void
    {
        $user = User::create([
            'name' => 'Unsigned Print Staff',
            'email' => 'unsigned.print'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        $print = $this->printAreaHtml($this->actingAs($user)->get(route('terms')));

        $this->assertStringContainsString('ACKNOWLEDGEMENT', $print);
        $this->assertStringContainsString('Signature', $print);
        $this->assertStringContainsString('Printed Name', $print);
        $this->assertStringContainsString('Role', $print);
        $this->assertStringContainsString('Date', $print);
        $this->assertStringContainsString('<span class="dpp-sig-v is-plain">Staff</span>', $print);

        $this->assertStringNotContainsString('Unsigned Print Staff', $print);
        $this->assertStringNotContainsString(now()->format('F j, Y'), $print);
        $this->assertStringNotContainsString('confidentiality/signatures/', $print);
        $this->assertStringNotContainsString('dpp-sig-img', $print);
    }

    public function test_terms_print_area_has_no_acknowledgement_for_guest(): void
    {
        $print = $this->printAreaHtml($this->get(route('terms')));

        $this->assertStringNotContainsString('ACKNOWLEDGEMENT', $print);
        $this->assertStringNotContainsString('Signature', $print);
        $this->assertStringNotContainsString('Printed Name', $print);
        $this->assertStringNotContainsString('dpp-sig', $print);
    }

    public function test_signed_card_shows_electronic_signature_receipt_without_internal_ids(): void
    {
        Storage::fake('supabase');

        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $this->signaturePng(),
            ])
            ->assertRedirect(route('terms'));

        $this->actingAs($staff)
            ->get(route('terms'))
            ->assertOk()
            ->assertSee('Electronic Signature')
            ->assertSee('Signature Recorded')
            ->assertSee('Signed by')
            ->assertSee('Signed')
            ->assertDontSee('Account ID');
    }

    public function test_acknowledge_requires_agreement_and_signature(): void
    {
        Storage::fake('supabase');

        $staff = User::create([
            'name' => 'Unsigned Staff',
            'email' => 'unsigned.staff'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->from(route('terms'))
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
            ])
            ->assertSessionHasErrors([
                'signature_data' => 'Please provide your electronic signature to continue.',
            ])
            ->assertRedirect(route('terms'));

        $this->actingAs($staff)
            ->from(route('terms'))
            ->post(route('terms.acknowledge.store'), [
                'signature_data' => $this->signaturePng(),
            ])
            ->assertSessionHasErrors([
                'agree' => 'Please check the acknowledgement box to continue.',
            ]);

        $this->assertNull($staff->fresh()->confidentiality_ack_version);
        $this->assertDatabaseMissing('signatures', ['user_id' => $staff->id]);
    }

    public function test_acknowledgement_requires_authentication(): void
    {
        $this->post(route('terms.acknowledge.store'), [
            'agree' => '1',
            'signature_data' => $this->signaturePng(),
        ])->assertRedirect(route('login'));
    }

    public function test_acknowledging_terms_shows_signed_confirmation(): void
    {
        Storage::fake('supabase');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $this->signaturePng(),
            ])
            ->assertRedirect(route('terms'));

        $this->actingAs($admin)
            ->get(route('terms'))
            ->assertOk()
            ->assertSee('Signed')
            ->assertSee('Terms Admin')
            ->assertSee('Admin');
    }

    public function test_terms_page_shows_role_specific_signature_label(): void
    {
        $this->actingAs($this->internal(User::ROLE_STAFF, 'Terms Staff'))
            ->get(route('terms'))
            ->assertOk()
            ->assertSee('Staff Signature');

        $this->actingAs($this->internal(User::ROLE_SUPERVISOR, 'Terms Supervisor'))
            ->get(route('terms'))
            ->assertOk()
            ->assertSee('Supervisor Signature');

        $this->actingAs($this->admin())
            ->get(route('terms'))
            ->assertOk()
            ->assertSee('Admin Signature');
    }

    public function test_acknowledgement_records_id_role_version_signature_and_signed_at(): void
    {
        Storage::fake('supabase');

        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $this->signaturePng(),
            ])
            ->assertRedirect(route('terms'));

        $signature = Signature::where('user_id', $staff->id)->first();
        $this->assertNotNull($signature);
        $this->assertSame(User::ROLE_STAFF, $signature->role);
        $this->assertSame(EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION, $signature->policy_version);
        $this->assertNotNull($signature->signed_at);
        $this->assertStringStartsWith('signatures/', $signature->signature_path);
        Storage::disk('supabase')->assertExists($signature->signature_path);

        $this->assertSame(EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION, $staff->fresh()->confidentiality_ack_version);
    }
}