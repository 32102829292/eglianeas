<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingShareTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Share Admin',
            'email' => 'shareadmin'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(): User
    {
        return User::create([
            'name' => 'Share Client',
            'email' => 'shareclient'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    private function paidBilling(User $client, int $quarter = 1, int $year = 2026): Billing
    {
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = $quarter;
        $billing->year = $year;
        $billing->period_label = '1ST QUARTER '.$year.' BILLING';
        $billing->cash_in = 100;
        $billing->status = Billing::STATUS_PAID;
        $billing->paid_at = now();
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        BillingLineItem::create([
            'billing_id' => $billing->id,
            'category' => BillingLineItem::CATEGORY_PROFESSIONAL_FEE,
            'form_type' => null,
            'label' => 'Professional Fee',
            'month' => 1,
            'amount' => 500.00,
            'fee_rate_id' => null,
        ]);

        $billing->recomputeTotal();
        $billing->save();

        return $billing;
    }

    public function test_share_url_is_generated_from_configured_app_url_not_the_request_host(): void
    {
        config(['app.url' => 'https://billing.egliane.test']);

        $admin = $this->admin();
        $client = $this->client();
        $billing = $this->paidBilling($client);

        $response = $this->actingAs($admin)->get(route('admin.billing.receipt', $billing));

        $response->assertOk();
        $response->assertSee('data-url="https://billing.egliane.test/client/billing/'.$billing->id.'"', false);

        $response->assertSee('data-client="'.$client->name.'"', false);
        $response->assertSee('data-period="1ST QUARTER 2026 BILLING"', false);
    }

    public function test_share_menu_exposes_the_five_channels_and_no_private_data_attributes(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $billing = $this->paidBilling($client);

        $response = $this->actingAs($admin)->get(route('admin.billing.receipt', $billing));

        $response->assertOk();

        foreach (['shareMessengerBtn', 'shareViberBtn', 'shareTelegramBtn', 'shareEmailBtn', 'shareCopyBtn'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }

        $response->assertDontSee('shareSmsBtn');
        $response->assertDontSee('data-total=', false);
        $response->assertDontSee('data-due=', false);
        $response->assertDontSee('data-paid=', false);
        $response->assertDontSee('data-gcash=', false);
    }

    public function test_inline_script_is_valid_javascript_not_html_escaped(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->paidBilling($client);

        $response = $this->actingAs($admin)->get(route('admin.billing.receipt', 1));
        $response->assertOk();

        $html = $response->getContent();
        // Extract the inline script belonging to this page (the one with the share logic).
        // The @push('scripts') block renders after the button, so search forward from $pos.
        $needle = 'shareBillingBtn';
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos);
        $start = strpos($html, '<script>', $pos);
        $this->assertNotFalse($start);
        $end = strpos($html, '</script>', $start);
        $this->assertNotFalse($end);
        $script = substr($html, $start + strlen('<script>'), $end - $start - strlen('<script>'));

        // The page must emit PHP data into JS as valid JSON, not HTML-escaped (&quot; etc).
        $this->assertStringNotContainsString('&quot;', $script);
        $this->assertStringNotContainsString('&amp;', $script);
        $this->assertStringNotContainsString('&lt;', $script);

        // And it must pass a real JS parse (Node is available on this machine).
        $scriptFile = sys_get_temp_dir() . '/billing_receipt_check_' . uniqid() . '.js';
        file_put_contents($scriptFile, $script);
        exec('node --check ' . escapeshellarg($scriptFile) . ' 2>&1', $out, $code);
        @unlink($scriptFile);
        $this->assertSame(0, $code, 'inline script failed JS syntax check: ' . implode(' ', $out));
    }

    public function test_place_menu_translates_viewport_coords_to_containing_block(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->paidBilling($client);

        $response = $this->actingAs($admin)->get(route('admin.billing.receipt', 1));
        $response->assertOk();
        $html = $response->getContent();
        $needle = 'shareBillingBtn';
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos);
        $start = strpos($html, '<script>', $pos);
        $this->assertNotFalse($start);
        $end = strpos($html, '</script>', $start);
        $script = substr($html, $start + strlen('<script>'), $end - $start - strlen('<script>'));

        // The placeMenu clamp must translate viewport coordinates into the containing block
        // coordinates before writing style.left/style.top (else the menu flies off the screen).
        $this->assertStringContainsString("var wr = wrap.getBoundingClientRect();", $script);
        $this->assertStringContainsString("menu.style.left = (left - wr.left) + 'px';", $script);
        $this->assertStringContainsString("menu.style.top = (top - wr.top) + 'px';", $script);
    }

    public function test_all_five_share_url_formats_are_built_in_the_client_script(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $billing = $this->paidBilling($client);

        $response = $this->actingAs($admin)->get(route('admin.billing.receipt', $billing));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString(
            'https://www.messenger.com/share/?link=' . "' + encodeURIComponent(statementUrl())",
            $html
        );

        $this->assertStringContainsString(
            'https://t.me/share/url?url=' . "' + encodeURIComponent(statementUrl()) + '&text=' + encodeURIComponent(shortMessage())",
            $html
        );

        $this->assertStringContainsString(
            "'viber://forward?text=' + encodeURIComponent(buildMessage())",
            $html
        );

        $this->assertStringContainsString(
            "'mailto:?subject=' + encodeURIComponent('Egliane Billing Statement') + '&body=' + encodeURIComponent(buildMessage())",
            $html
        );

        $this->assertStringContainsString("navigator.clipboard", $html);
        $this->assertStringContainsString("document.execCommand('copy')", $html);
        $this->assertStringContainsString("navigator.share", $html);

        $response->assertDontSee('data-total=', false);
        $response->assertDontSee('data-due=', false);
        $response->assertDontSee('data-paid=', false);
        $response->assertDontSee('data-gcash=', false);
        $response->assertDontSee('shareSmsBtn');
    }

    public function test_a_client_cannot_open_another_clients_statement(): void
    {
        $owner = $this->client();
        $intruder = $this->client();
        ClientSurveyResponse::create([
            'user_id' => $intruder->id,
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => null,
            'submitted_at' => now(),
        ]);
        $billing = $this->paidBilling($owner);

        $this->actingAs($intruder)->get(route('client.billing.show', $billing))->assertForbidden();
    }

    public function test_guests_are_redirected_away_from_the_statement(): void
    {
        $owner = $this->client();
        $billing = $this->paidBilling($owner);

        $this->get(route('client.billing.show', $billing))->assertRedirect(route('login'));
    }
}