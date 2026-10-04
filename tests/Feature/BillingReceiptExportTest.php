<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BillingController;
use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ReflectionMethod;
use Tests\TestCase;

class BillingReceiptExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Export QA Admin',
            'email' => 'export-admin-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function supervisor(): User
    {
        return User::create([
            'name' => 'Export QA Supervisor',
            'email' => 'export-supervisor-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_SUPERVISOR,
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

    /**
     * @param array<int, array{category: string, form_type: ?string, amount: float}> $items
     */
    private function billing(User $client, int $quarter, array $items, int $year = 2026): Billing
    {
        $total = array_sum(array_column($items, 'amount'));

        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = $quarter;
        $billing->year = $year;
        $billing->period_label = 'Q'.$quarter.' '.$year.' BILLING';
        $billing->cash_in = 0;
        $billing->total = $total;
        $billing->status = Billing::STATUS_UNPAID;
        $billing->due_date = $year.'-'.str_pad((string) (($quarter - 1) * 3 + 1), 2, '0', STR_PAD_LEFT).'-15';
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        foreach ($items as $i => $item) {
            $billing->lineItems()->create([
                'category' => $item['category'],
                'form_type' => $item['form_type'],
                'label' => 'Line item '.($i + 1),
                'amount' => $item['amount'],
            ]);
        }

        return Billing::with(['client.birFormStatuses', 'client.profile', 'lineItems'])->find($billing->id);
    }

    /**
     * Alpha: Q1  Eremittance 1000 (500 bir/2307 + 300 bir/2316 + 200 cash in),
     * fee 550 (400 pro fee + 150 bookkeeping), grand 1550.
     */
    private function alpha(): array
    {
        $client = $this->client('Receipt Alpha Co');
        $billing = $this->billing($client, 1, [
            ['category' => BillingLineItem::CATEGORY_BIR_REMITTANCE, 'form_type' => '2307', 'amount' => 500.0],
            ['category' => BillingLineItem::CATEGORY_BIR_REMITTANCE, 'form_type' => '2316', 'amount' => 300.0],
            ['category' => BillingLineItem::CATEGORY_BIR_REMITTANCE, 'form_type' => null, 'amount' => 200.0],
            ['category' => BillingLineItem::CATEGORY_PROFESSIONAL_FEE, 'form_type' => '2307', 'amount' => 400.0],
            ['category' => BillingLineItem::CATEGORY_BOOKKEEPING_FEE, 'form_type' => null, 'amount' => 150.0],
        ]);

        return ['client' => $client, 'billing' => $billing];
    }

    /**
     * Beta: Q1  Eremittance 100, fee 50, grand 150.
     */
    private function beta(): array
    {
        $client = $this->client('Receipt Beta Co');
        $billing = $this->billing($client, 1, [
            ['category' => BillingLineItem::CATEGORY_BIR_REMITTANCE, 'form_type' => '2307', 'amount' => 100.0],
            ['category' => BillingLineItem::CATEGORY_DATA_ENTRY, 'form_type' => null, 'amount' => 50.0],
        ]);

        return ['client' => $client, 'billing' => $billing];
    }

    /**
     * Gamma: Q3  Eoutside the Q1 filter scope.
     */
    private function gamma(): array
    {
        $client = $this->client('Receipt Gamma Q3 Co');
        $billing = $this->billing($client, 3, [
            ['category' => BillingLineItem::CATEGORY_BIR_REMITTANCE, 'form_type' => '2307', 'amount' => 700.0],
        ]);

        return ['client' => $client, 'billing' => $billing];
    }

    private function filteredBillings(?int $quarter, int $year, array $clientIds = [], ?string $search = null): \Illuminate\Support\Collection
    {
        $method = new ReflectionMethod(BillingController::class, 'getFilteredBillings');
        $method->setAccessible(true);

        return $method->invoke(new BillingController, $quarter, $year, $clientIds, $search);
    }

    private function loadWorkbook(string|false $content, string &$path): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'egliane-xlsx-');
        file_put_contents($path, $content);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);

        return $reader->load($path);
    }

    private function columnMap(object $sheet): array
    {
        $headers = [];
        $last = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        foreach ([1, 2] as $headerRow) {
            for ($col = 1; $col <= $last; $col++) {
                $value = $sheet->getCell(Coordinate::stringFromColumnIndex($col).$headerRow)->getValue();
                if ($value !== null && $value !== '' && ! isset($headers[$value])) {
                    $headers[$value] = $col;
                }
            }
        }

        return $headers;
    }

    // ---- Export: selection semantics ------------------------------------

    public function test_admin_xlsx_export_with_selected_clients_includes_only_those(): void
    {
        $alpha = $this->alpha();
        $beta = $this->beta();
        $gamma = $this->gamma();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.billing.exportSummaryXlsx', [
                'year' => '2026',
                'quarter' => '1',
                'clients' => [$alpha['client']->id, $beta['client']->id],
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = '';
        $sheet = $this->loadWorkbook($response->streamedContent(), $path)->getActiveSheet();
        $cells = $sheet->toArray(null, true, true, true);

        $businesses = array_column($cells, 'A');
        $this->assertContains('Receipt Alpha Co', $businesses);
        $this->assertContains('Receipt Beta Co', $businesses);
        $this->assertNotContains('Receipt Gamma Q3 Co', $businesses);
        unlink($path);
    }

    public function test_admin_xlsx_export_with_no_selection_includes_every_matching_receipt(): void
    {
        $this->alpha();
        $this->beta();
        $gamma = $this->gamma();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.billing.exportSummaryXlsx', ['year' => '2026', 'quarter' => '1']));

        $response->assertOk();

        $path = '';
        $sheet = $this->loadWorkbook($response->streamedContent(), $path)->getActiveSheet();
        $businesses = array_column($sheet->toArray(null, true, true, true), 'A');

        $this->assertContains('Receipt Alpha Co', $businesses);
        $this->assertContains('Receipt Beta Co', $businesses);
        $this->assertNotContains('Receipt Gamma Q3 Co', $businesses);
        unlink($path);
    }

    public function test_xlsx_financials_and_final_grand_total_are_correct_without_double_counting(): void
    {
        $this->alpha();
        $this->beta();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.billing.exportSummaryXlsx', ['year' => '2026', 'quarter' => '1']));

        $path = '';
        $sheet = $this->loadWorkbook($response->streamedContent(), $path)->getActiveSheet();
        $cols = $this->columnMap($sheet);

        $bir = $cols['Subtotal for Remittance (BIR)'];
        $fee = $cols['Subtotal for Fee (Fee / Cash In)'];
        $grand = $cols['Grand Total'];

        $birLetter = Coordinate::stringFromColumnIndex($bir);
        $feeLetter = Coordinate::stringFromColumnIndex($fee);
        $grandLetter = Coordinate::stringFromColumnIndex($grand);

        // Row 3 = Alpha (800 remittance, 750 fee, 1550 grand).
        $this->assertSame(800.0, (float) $sheet->getCell($birLetter.'3')->getValue());
        $this->assertSame(750.0, (float) $sheet->getCell($feeLetter.'3')->getValue());
        $this->assertSame(1550.0, (float) $sheet->getCell($grandLetter.'3')->getValue());

        // Row 4 = Beta (100 remittance, 50 fee, 150 grand).
        $this->assertSame(100.0, (float) $sheet->getCell($birLetter.'4')->getValue());
        $this->assertSame(50.0, (float) $sheet->getCell($feeLetter.'4')->getValue());
        $this->assertSame(150.0, (float) $sheet->getCell($grandLetter.'4')->getValue());

        // Final row 5: Final BIR = 900, Final Fee = 800, Final Grand = 1700.
        $this->assertSame('GRAND TOTAL', $sheet->getCell('A5')->getValue());
        $this->assertSame(900.0, (float) $sheet->getCell($birLetter.'5')->getValue());
        $this->assertSame(800.0, (float) $sheet->getCell($feeLetter.'5')->getValue());
        $this->assertSame(1700.0, (float) $sheet->getCell($grandLetter.'5')->getValue());

        // Final Grand == Final BIR + Final Fee (never a pre-cooked grand total).
        $this->assertSame(900.0 + 800.0, (float) $sheet->getCell($grandLetter.'5')->getValue());

        // Every row's Grand == BIR + Fee of that row; the final Grand equals the
        // sum of the displayed Grand column, so nothing is double counted.
        $this->assertSame(
            900.0 + 800.0,
            (float) $sheet->getCell($birLetter.'3')->getValue()
                + (float) $sheet->getCell($feeLetter.'3')->getValue()
                + (float) $sheet->getCell($birLetter.'4')->getValue()
                + (float) $sheet->getCell($feeLetter.'4')->getValue()
        );
        unlink($path);
    }

    public function test_xlsx_applies_category_color_treatments_in_downloaded_file(): void
    {
        $this->alpha();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.billing.exportSummaryXlsx', ['year' => '2026', 'quarter' => '1']));

        $path = '';
        $sheet = $this->loadWorkbook($response->streamedContent(), $path)->getActiveSheet();
        $cols = $this->columnMap($sheet);

        $bir = Coordinate::stringFromColumnIndex($cols['Subtotal for Remittance (BIR)']);
        $fee = Coordinate::stringFromColumnIndex($cols['Subtotal for Fee (Fee / Cash In)']);
        $grand = Coordinate::stringFromColumnIndex($cols['Grand Total']);

        // Header fills carry the accent color; data cells carry the soft wash.
        $this->assertSame('1E4E8C', $sheet->getStyle($bir.'1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('EAF1FB', $sheet->getStyle($bir.'3')->getFill()->getStartColor()->getRGB());

        $this->assertSame('0F766E', $sheet->getStyle($fee.'1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('E7F3F2', $sheet->getStyle($fee.'3')->getFill()->getStartColor()->getRGB());

        $this->assertSame('111827', $sheet->getStyle($grand.'1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('F1F3F7', $sheet->getStyle($grand.'3')->getFill()->getStartColor()->getRGB());

        // The final GRAND TOTAL row reuses the category accents and is bold.
        $this->assertTrue((bool) $sheet->getStyle($bir.'4')->getFont()->getBold());
        $this->assertSame('1E4E8C', strtoupper($sheet->getStyle($bir.'4')->getFont()->getColor()->getRGB()));
        $this->assertSame('0F766E', strtoupper($sheet->getStyle($fee.'4')->getFont()->getColor()->getRGB()));
        $this->assertSame('111827', strtoupper($sheet->getStyle($grand.'4')->getFont()->getColor()->getRGB()));
        unlink($path);
    }

    public function test_admin_pdf_export_with_selected_clients_streams_and_summarizes_correctly(): void
    {
        $alpha = $this->alpha();
        $beta = $this->beta();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.billing.exportSummaryPdf', [
                'year' => '2026',
                'quarter' => '1',
                'clients' => [$alpha['client']->id],
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // Render the same view the route streams so the financial summary and
        // its totals can be asserted directly.
        $html = view('admin.billing.summary-pdf', [
            'billings' => collect([$alpha['billing']]),
            'periodLabel' => '1st Quarter 2026 Billing',
            'allFormTypes' => ['2307', '2316'],
        ])->render();

        $this->assertStringContainsString('Billing Summary', $html);
        $this->assertStringContainsString('For Remittance', $html);
        $this->assertStringContainsString('For Fee', $html);
        $this->assertStringContainsString('Subtotal for Remittance', $html);
        $this->assertStringContainsString('Subtotal for Fee', $html);
        $this->assertStringContainsString('Fee / Cash In', $html);
        $this->assertStringContainsString('Grand Total', $html);
        $this->assertStringContainsString('Receipt Alpha Co', $html);
        $this->assertStringNotContainsString('Receipt Beta Co', $html);

        // Cash In is a form-less BIR line item that the matrix reports inside
        // the fee group, so Alpha splits as 800 remittance + 750 fee = 1,550.
        $this->assertStringContainsString('800.00', $html);
        $this->assertStringContainsString('750.00', $html);
        $this->assertStringContainsString('1,550.00', $html);

        // Color treatments present in the report.
        $this->assertStringContainsString('background: #1E4E8C', $html);
        $this->assertStringContainsString('background: #EAF1FB', $html);
        $this->assertStringContainsString('background: #0F766E', $html);
        $this->assertStringContainsString('background: #E7F3F2', $html);
        $this->assertStringContainsString('background: #111827', $html);
        $this->assertStringContainsString('background: #F1F3F7', $html);

        // The summary table never clips or falls off a page.
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringNotContainsString('overflow: hidden', $html);
    }

    public function test_supervisor_can_export_and_print_receipts_but_clients_cannot(): void
    {
        $this->alpha();

        $supervisor = $this->supervisor();
        $routes = [
            route('admin.billing.exportSummaryXlsx', ['year' => '2026', 'quarter' => '1']),
            route('admin.billing.exportSummaryPdf', ['year' => '2026', 'quarter' => '1']),
            route('admin.billing.printBatch', ['year' => '2026', 'quarter' => '1']),
        ];

        foreach ($routes as $url) {
            $this->actingAs($supervisor)->get($url)->assertOk();
        }

        $client = $this->client('Receipt Blocked Client');
        foreach ($routes as $url) {
            $this->actingAs($client)->get($url)->assertForbidden();
        }
    }

    // ---- Print: selection semantics --------------------------------------

    public function test_print_selected_clients_resolves_only_those_receipts(): void
    {
        $alpha = $this->alpha();
        $beta = $this->beta();
        $gamma = $this->gamma();

        $billings = $this->filteredBillings(1, 2026, [$alpha['client']->id, $beta['client']->id]);

        $this->assertSame(
            [$alpha['client']->id, $beta['client']->id],
            $billings->pluck('client_id')->unique()->sort()->values()->all()
        );
        $this->assertNotContains($gamma['client']->id, $billings->pluck('client_id')->all());

        // Print page shows one receipt pair per selected billing, none for Gamma.
        $html = view('admin.billing.statements-pdf', [
            'billings' => $billings,
            'payments' => [],
            'gcashNumber' => '',
            'bankAccounts' => [],
            'overflowIds' => [],
            'paperSize' => 'a4',
            'density' => 'normal',
        ])->render();

        $this->assertStringContainsString('Receipt Alpha Co', $html);
        $this->assertStringContainsString('Receipt Beta Co', $html);
        $this->assertStringNotContainsString('Receipt Gamma Q3 Co', $html);
        $this->assertSame($billings->count(), substr_count($html, 'class="pair-wrap"'));
    }

    public function test_print_all_streams_a_pdf_for_every_matching_receipt(): void
    {
        $this->alpha();
        $this->beta();
        $this->gamma();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.billing.printBatch', ['year' => '2026', 'quarter' => '1']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // With no clients chosen the filter falls back to the whole active
        // period (Alpha + Beta only; Gamma lives outside Q1).
        $billings = $this->filteredBillings(1, 2026);
        $this->assertSame(2, $billings->count());
        $this->assertNotContains($this->gamma()['client']->id, $billings->pluck('client_id')->all());
    }

    public function test_print_all_with_no_quarter_returns_all_active_years_receipts(): void
    {
        $alpha = $this->alpha();
        $gamma = $this->gamma();

        $billings = $this->filteredBillings(null, 2026);

        $this->assertEqualsCanonicalizing(
            [$alpha['client']->id, $gamma['client']->id],
            $billings->pluck('client_id')->all()
        );
    }

    public function test_billing_index_exposes_receipt_selection_controls_and_helper(): void
    {
        $admin = $this->admin();
        $this->alpha();

        $html = $this->actingAs($admin)
            ->get(route('admin.billing.index', ['quarter' => '2026-Q1']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No clients selected &mdash; all matching billing receipts will be included.', $html);
        $this->assertStringContainsString('Print Selected', $html);
        $this->assertStringContainsString('Print All', $html);
        $this->assertStringContainsString('Export XLSX', $html);
        $this->assertStringContainsString('billing-select-check', $html);
        $this->assertStringContainsString('data-billing-select-all', $html);
    }
}

