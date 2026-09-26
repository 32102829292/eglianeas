<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BillingController;
use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\BillingPaymentDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPrintBatchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Batch QA Admin',
            'email' => 'batch-admin-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $label): User
    {
        return User::create([
            'name' => $label,
            'email' => strtolower(str_replace(' ', '', $label)).uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    private function billing(User $client, int $categories = 1, int $items = 3, int $quarter = 1): Billing
    {
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = $quarter;
        $billing->year = 2026;
        $billing->period_label = 'Q'.$quarter.' 2026 BILLING';
        $billing->cash_in = 0;
        $billing->total = 1000.00;
        $billing->status = Billing::STATUS_UNPAID;
        $billing->due_date = '2026-01-15';
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        $categories = max(1, min($categories, count(BillingLineItem::CATEGORIES)));
        for ($i = 0; $i < $items; $i++) {
            $billing->lineItems()->create([
                'category' => array_keys(BillingLineItem::CATEGORIES)[$i % $categories],
                'label' => 'Line item '.($i + 1),
                'amount' => 100.0,
            ]);
        }

        return $billing;
    }

    public function test_block_height_mm_responds_to_payment_payload(): void
    {
        $this->assertSame(0.0, BillingPaymentDetails::blockHeightMm([]));
        $this->assertSame(0.0, BillingPaymentDetails::blockHeightMm([
            'gcash_number' => '',
            'gcash_qr' => null,
            'banks' => [],
            'has' => false,
        ]));

        // GCash number only: title + one line + padding + safety.
        $this->assertSame(8.7, BillingPaymentDetails::blockHeightMm([
            'gcash_number' => '09171234567',
            'gcash_qr' => null,
            'banks' => [],
            'has' => true,
        ]));

        // QR reserves a fixed 8mm cell regardless of line count.
        $this->assertSame(14.2, BillingPaymentDetails::blockHeightMm([
            'gcash_number' => '09171234567',
            'gcash_qr' => 'data:image/png;base64,abc',
            'banks' => [],
            'has' => true,
        ]));

        $this->assertSame(14.2, BillingPaymentDetails::blockHeightMm([
            'gcash_number' => '09171234567',
            'gcash_qr' => 'data:image/png;base64,abc',
            'banks' => [[
                'bank_name' => 'BDO',
                'account_name' => 'Egliane',
                'account_number' => '123',
                'label' => 'BDO · 123 · Egliane',
                'qr' => null,
            ]],
            'has' => true,
        ]));

        // Three banks without GCash build height from the text lines.
        $this->assertSame(13.7, BillingPaymentDetails::blockHeightMm([
            'gcash_number' => '',
            'gcash_qr' => null,
            'banks' => [
                ['label' => 'BDO', 'qr' => null],
                ['label' => 'BPI', 'qr' => null],
                ['label' => 'UB', 'qr' => null],
            ],
            'has' => true,
        ]));

        // A bank-level QR reserves the 8mm cell too.
        $this->assertSame(14.2, BillingPaymentDetails::blockHeightMm([
            'gcash_number' => '',
            'gcash_qr' => null,
            'banks' => [[
                'label' => 'BDO',
                'qr' => 'data:image/png;base64,abc',
            ]],
            'has' => true,
        ]));
    }

    public function test_choose_batch_density_fits_whole_batch_on_one_page(): void
    {
        $client = $this->client('Budget Co');
        $billings = collect([$this->billing($client, 1, 2, 1), $this->billing($client, 1, 2, 2)]);

        $gap = 3.5;
        $normalTotal = round(2 * BillingController::pairHeightMm($billings->first(), 'normal') + $gap, 2);
        $compactTotal = round(2 * BillingController::pairHeightMm($billings->first(), 'compact') + $gap, 2);

        // Enough room for both pairs at full size → normal, nothing flagged.
        [$density, $overflow] = BillingController::chooseBatchDensity($billings, $normalTotal);
        $this->assertSame('normal', $density);
        $this->assertSame([], $overflow);

        // A tighter page only fits them at compact size → tier drops, still fits.
        [$density, $overflow] = BillingController::chooseBatchDensity($billings, $compactTotal);
        $this->assertSame('compact', $density);
        $this->assertSame([], $overflow);
    }

    public function test_choose_batch_density_flags_statements_taller_than_one_page(): void
    {
        $client = $this->client('Oversize Co');
        $billing = $this->billing($client, 3, 20);

        // Even at its smallest pair the statement exceeds the page content.
        $pageContent = round(BillingController::pairHeightMm($billing, 'tiny') - 5.0, 2);
        [$density, $overflow] = BillingController::chooseBatchDensity(collect([$billing]), $pageContent);
        $this->assertSame('tiny', $density);
        $this->assertSame([$billing->id], $overflow);

        // With a little more room it fits at tiny without being flagged.
        $pageContent = round(BillingController::pairHeightMm($billing, 'tiny') + 5.0, 2);
        [$density, $overflow] = BillingController::chooseBatchDensity(collect([$billing]), $pageContent);
        $this->assertSame('tiny', $density);
        $this->assertSame([], $overflow);
    }

    public function test_statements_pdf_renders_payment_details_inside_every_cell(): void
    {
        Setting::set('gcash_number', '09171234567');
        Setting::set('bank_accounts', [[
            'bank_name' => 'BDO',
            'account_name' => 'Harris Egliane',
            'account_number' => '1234-5678-90',
        ]]);

        $client = $this->client('Render Co');
        $billings = collect([$this->billing($client, 1, 3)]);

        $payments = BillingPaymentDetails::forPdf();

        $html = view('admin.billing.statements-pdf', [
            'billings' => $billings,
            'payments' => $payments,
            'paperSize' => 'a4',
            'density' => 'normal',
            'overflowIds' => [],
        ])->render();

        $this->assertStringContainsString('Payment Details</div>', $html);
        $this->assertStringContainsString('<b>GCash</b>', $html);
        $this->assertStringContainsString('09171234567', $html);
        $this->assertStringContainsString('BDO', $html);
        $this->assertStringContainsString('Harris Egliane', $html);

        // Two copies per billing row: the block is included twice.
        $this->assertSame(2, substr_count($html, 'class="cell-payments"'));

        // Natural-height layout: pairs must never be clipped or overflow-hidden,
        // and there must be no fixed-height slot grid or OVERSIZE warning.
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringNotContainsString('overflow: hidden', $html);
        $this->assertStringNotContainsString('.slot {', $html);
        $this->assertStringNotContainsString('OVERSIZE', $html);
        $this->assertStringNotContainsString('taller than one page', $html);
    }

    public function test_statements_pdf_omits_payment_block_when_no_settings(): void
    {
        $client = $this->client('Empty Co');
        $billings = collect([$this->billing($client, 1, 3)]);

        $payments = BillingPaymentDetails::forPdf();
        $this->assertFalse($payments['has']);
        $payBlockMm = BillingPaymentDetails::blockHeightMm($payments);
        $this->assertSame(0.0, $payBlockMm);

        $html = view('admin.billing.statements-pdf', [
            'billings' => $billings,
            'payments' => $payments,
            'paperSize' => 'a4',
            'density' => 'normal',
            'overflowIds' => [],
        ])->render();

        $this->assertStringNotContainsString('class="cell-payments"', $html);
        $this->assertStringNotContainsString('Payment Details', $html);

        // Layout contract holds regardless of payment settings.
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringNotContainsString('overflow: hidden', $html);
    }

    public function test_print_batch_route_streams_a_pdf(): void
    {
        Setting::set('gcash_number', '09171234567');
        Setting::set('bank_accounts', [[
            'bank_name' => 'BDO',
            'account_name' => 'Harris Egliane',
            'account_number' => '1234-5678-90',
        ]]);

        $admin = $this->admin();
        $client = $this->client('Route Co');
        $billing = $this->billing($client, 1, 3);

        $response = $this->actingAs($admin)
            ->get(route('admin.billing.printBatch', ['ids' => [$billing->id]]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}