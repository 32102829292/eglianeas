<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\CashDailyLine;
use App\Models\CashDailyPosition;
use App\Models\CashDailyReceipt;
use App\Models\CashDailyReport;
use App\Models\CashDailyReportLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Regression coverage for the date-based Cash Daily Position worksheet:
 * "Edit/Add Lines" must load and update the selected report in place, and
 * receipts must upload, preview and delete for both client-scoped and
 * date-based (client_id = null) positions.
 *
 * The suite uses the project's normal test database (SQLite :memory: from
 * phpunit.xml), so it never touches the configured remote database.
 */
class CashDailyPositionEditReceiptTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $writtenFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function position(string $date, ?int $clientId = null, float $beginning = 0): CashDailyPosition
    {
        return CashDailyPosition::create([
            'client_id' => $clientId,
            'position_date' => $date,
            'beginning_balance' => $beginning,
            'budget' => 0,
        ]);
    }

    private function trackFiles(CashDailyPosition $position): void
    {
        foreach ($position->receipts()->get() as $receipt) {
            $this->writtenFiles[] = storage_path('app/'.$receipt->file_path);
        }
    }

    private function realPdf(string $name = 'receipt.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cdp_pdf_');
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    // ---- Edit / Add Lines ------------------------------------------------

    public function test_edit_opens_the_exact_selected_report_not_the_latest_one(): void
    {
        $admin = $this->admin();

        $older = $this->position('2026-11-10', null, 111);
        CashDailyLine::create([
            'cash_daily_position_id' => $older->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Older receipt',
            'amount' => 10,
        ]);

        $newer = $this->position('2026-11-11', null, 222);
        CashDailyLine::create([
            'cash_daily_position_id' => $newer->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Newer receipt',
            'amount' => 20,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.create', ['date' => '2026-11-10']));

        $response->assertOk()
            ->assertSee('Older receipt')
            ->assertDontSee('Newer receipt')
            ->assertSee('name="position_id" value="'.$older->id.'"', false)
            ->assertSee('Save Report');

        $this->assertSame(2, CashDailyPosition::count());
    }

    public function test_editing_updates_the_same_report_and_reconciles_all_line_blocks(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $position = $this->position('2026-11-10', null, 100);
        $addition = CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'client_id' => $client->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Original cash',
            'amount' => 50,
        ]);
        $salary = CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_SALARY,
            'label' => 'Original salary',
            'amount' => 20,
        ]);
        $expense = CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_EXPENSE,
            'label' => 'Original expense',
            'amount' => 10,
        ]);
        $receipt = CashDailyReceipt::create([
            'cash_daily_position_id' => $position->id,
            'file_name' => 'existing.pdf',
            'file_type' => 'pdf',
            'file_path' => 'private/cash_daily_receipts/existing.pdf',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cash-daily-position.store-daily'), [
            'position_id' => $position->id,
            'position_date' => '2026-11-10',
            'beginning_balance' => 250,
            'actual_cash' => 300,
            'additions' => [
                ['id' => $addition->id, 'label' => 'Edited cash', 'amount' => 75],
                ['label' => 'New cash', 'amount' => 25],
            ],
            'salaries' => [],
            'expenses' => [
                ['id' => $expense->id, 'label' => 'Edited expense', 'amount' => 15],
            ],
        ]);

        $response->assertRedirect(route('admin.cash-daily-position.show', ['date' => '2026-11-10']))
            ->assertSessionHas('status');

        $position->refresh();

        $this->assertSame(1, CashDailyPosition::count(), 'Editing must not create a second report.');
        $this->assertSame('2026-11-10', $position->position_date->format('Y-m-d'));
        $this->assertSame('250.00', $position->beginning_balance);
        $this->assertSame('300.00', $position->actual_cash);

        // Existing addition updated in place, keeping its client link.
        $addition->refresh();
        $this->assertSame('Edited cash', $addition->label);
        $this->assertSame('75.00', $addition->amount);
        $this->assertSame($client->id, (int) $addition->client_id);

        // New line added, removed salary deleted, expense edited.
        $this->assertSame(2, $position->lines()->where('type', CashDailyLine::TYPE_ADDITION)->count());
        $this->assertSame(0, $position->lines()->where('type', CashDailyLine::TYPE_SALARY)->count());
        $this->assertFalse(CashDailyLine::whereKey($salary->id)->exists());
        $this->assertSame('Edited expense', $expense->refresh()->label);

        // Receipt attachments survive an edit.
        $this->assertTrue(CashDailyReceipt::whereKey($receipt->id)->exists());
        $this->assertSame(1, $position->receipts()->count());
    }

    public function test_duplicate_date_protection_still_blocks_a_new_report_but_not_an_edit(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-10', null, 100);

        $this->actingAs($admin)->post(route('admin.cash-daily-position.store-daily'), [
            'position_date' => '2026-11-10',
            'beginning_balance' => 999,
        ])->assertRedirect(route('admin.cash-daily-position.show', ['date' => '2026-11-10']))
            ->assertSessionHas('error');

        $this->assertSame(1, CashDailyPosition::count());
        $this->assertSame('100.00', $position->refresh()->beginning_balance);
    }

    // ---- Receipts --------------------------------------------------------

    public function test_multiple_images_and_a_pdf_can_be_uploaded_to_a_date_based_report(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-12');

        $response = $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            [
                'receipts' => [
                    UploadedFile::fake()->image('first.jpg', 120, 120),
                    UploadedFile::fake()->image('second.png', 120, 120),
                    $this->realPdf('third.pdf'),
                ],
            ],
        );

        $response->assertRedirect()->assertSessionHas('status');

        $receipts = $position->receipts()->get();
        $this->assertCount(3, $receipts);
        $this->assertSame(['jpg', 'pdf', 'png'], $receipts->pluck('file_type')->sort()->values()->all());

        foreach ($receipts as $receipt) {
            $this->assertStringStartsWith('private/cash_daily_receipts/', $receipt->file_path);
            $this->assertTrue(File::exists(storage_path('app/'.$receipt->file_path)));
            $this->assertStringNotContainsString('public/', $receipt->file_path);
        }

        $this->trackFiles($position);
    }

    public function test_additional_receipts_can_be_uploaded_later_and_survive_refresh(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-13');

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            ['receipts' => [UploadedFile::fake()->image('one.jpg', 60, 60)]],
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            ['receipts' => [UploadedFile::fake()->image('two.png', 60, 60)]],
        )->assertRedirect();

        $this->assertSame(2, $position->receipts()->count());

        $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.show', ['date' => '2026-11-13']))
            ->assertOk()
            ->assertSee('one.jpg')
            ->assertSee('two.png');

        $this->trackFiles($position);
    }

    public function test_image_and_pdf_previews_are_served_inline_without_public_paths(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-14');

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            [
                'receipts' => [
                    UploadedFile::fake()->image('photo.jpg', 100, 100),
                    $this->realPdf('document.pdf'),
                ],
            ],
        )->assertRedirect();

        $this->trackFiles($position);

        $image = $position->receipts()->where('file_type', 'jpg')->firstOrFail();
        $pdf = $position->receipts()->where('file_type', 'pdf')->firstOrFail();

        $imageResponse = $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.receipts.view', ['receipt' => $image->id]));
        $imageResponse->assertOk();
        $this->assertSame('image/jpeg', $imageResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('inline;', $imageResponse->headers->get('Content-Disposition'));

        $pdfResponse = $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.receipts.view', ['receipt' => $pdf->id]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('inline;', $pdfResponse->headers->get('Content-Disposition'));
    }

    public function test_receipt_can_be_downloaded_and_missing_files_fail_gracefully(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-14');

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            ['receipts' => [UploadedFile::fake()->image('download-me.jpg', 80, 80)]],
        )->assertRedirect();

        $this->trackFiles($position);

        $receipt = $position->receipts()->firstOrFail();
        $filePath = storage_path('app/'.$receipt->file_path);

        $download = $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.receipts.download', ['receipt' => $receipt->id]));
        $download->assertOk();
        $this->assertSame('image/jpeg', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('download-me.jpg', $download->headers->get('Content-Disposition'));

        File::delete($filePath);

        $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.receipts.view', ['receipt' => $receipt->id]))
            ->assertNotFound();
        $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.receipts.download', ['receipt' => $receipt->id]))
            ->assertNotFound();
    }

    public function test_receipt_ui_shows_upload_controls_table_columns_and_actions(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-18');

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            [
                'receipts' => [
                    UploadedFile::fake()->image('ui-check.png', 40, 40),
                    $this->realPdf('ui-check.pdf'),
                ],
            ],
        )->assertRedirect();

        $this->trackFiles($position);
        $receipt = $position->receipts()->where('file_type', 'png')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.show', ['date' => '2026-11-18']));

        $response->assertOk()
            ->assertSee('Choose Files')
            ->assertSee('Drag & drop receipts here')
            ->assertSee('Max 2 MB per file')
            ->assertSee('Up to 10 files')
            ->assertSee('Filename')
            ->assertSee('File Type')
            ->assertSee('Uploaded At')
            ->assertSee('View Receipt')
            ->assertSee(route('admin.cash-daily-position.receipts.download', ['receipt' => $receipt->id]), false)
            ->assertSee('data-tooltip="View Receipt"', false)
            ->assertSee('Delete this receipt?', false)
            ->assertDontSee('class="fas', false)
            ->assertDontSee('fa-eye', false)
            ->assertSee('data-icon="cloud-upload"', false)
            ->assertSee('data-icon="folder-open"', false)
            ->assertSee('data-icon="upload"', false)
            ->assertSee('data-icon="image"', false)
            ->assertSee('data-icon="file-text"', false)
            ->assertSee('data-icon="eye"', false)
            ->assertSee('data-icon="trash-2"', false)
            ->assertSee('data-icon="zoom-out"', false)
            ->assertSee('data-icon="zoom-in"', false)
            ->assertSee('data-icon="refresh-ccw"', false)
            ->assertSee('data-icon="rotate-cw"', false)
            ->assertSee('data-icon="download"', false)
            ->assertSee('data-icon="x"', false)
            ->assertSee('data-icon="arrow-left"', false)
            ->assertSee('data-icon="pencil"', false);

        $html = $response->getContent();

        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bfa-/', $html);

        preg_match_all('/<svg\b[^>]*>(.*?)<\/svg>/is', $html, $matches);
        $this->assertGreaterThan(20, count($matches[0]), 'The cash daily page should render the sidebar, header and receipt icons.');

        foreach ($matches[1] as $inner) {
            $this->assertMatchesRegularExpression(
                '/<(path|line|circle|rect|polyline|polygon|ellipse|use)\b/i',
                $inner,
                'A rendered SVG icon has no visible geometry.',
            );
        }
    }

    public function test_deleting_one_receipt_keeps_the_other_and_the_parent_report(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-15');

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            [
                'receipts' => [
                    UploadedFile::fake()->image('keep.jpg', 50, 50),
                    UploadedFile::fake()->image('remove.png', 50, 50),
                ],
            ],
        )->assertRedirect();

        $this->trackFiles($position);

        $keep = $position->receipts()->where('file_type', 'jpg')->firstOrFail();
        $remove = $position->receipts()->where('file_type', 'png')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.cash-daily-position.receipts.destroy', ['receipt' => $remove->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse(CashDailyReceipt::whereKey($remove->id)->exists());
        $this->assertFalse(File::exists(storage_path('app/'.$remove->file_path)));
        $this->assertTrue(CashDailyReceipt::whereKey($keep->id)->exists());
        $this->assertTrue(File::exists(storage_path('app/'.$keep->file_path)));
        $this->assertTrue(CashDailyPosition::whereKey($position->id)->exists());
    }

    public function test_receipt_upload_rejects_unsupported_file_types(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-16');

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            ['receipts' => [UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload')]],
        )->assertSessionHasErrors('receipts.0');

        $this->assertSame(0, $position->receipts()->count());
    }

    // ---- Remarks ----------------------------------------------------------

    public function test_remarks_are_additions_only_and_are_saved_loaded_and_edited(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $position = $this->position('2026-10-05', $client->id, 500);

        // Seed a salary line with notes to prove other blocks keep their notes
        // but never get a Remarks input in the ADD-only UI.
        $salary = CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_SALARY,
            'label' => 'Payroll',
            'amount' => 50,
            'notes' => 'Payroll notes that stay private.',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cash-daily-position.store-daily'), [
            'position_id' => $position->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'actual_cash' => null,
            'additions' => [
                [
                    'label' => 'Villabroza - Bookkeeping 4th of Oct',
                    'notes' => 'October bookkeeping payment',
                    'amount' => 400,
                ],
                [
                    'label' => 'Andres - Bookkeeping 1 and 2 of Oct',
                    'notes' => 'Bookkeeping collection',
                    'amount' => 600,
                ],
            ],
            'salaries' => [
                ['id' => $salary->id, 'label' => 'Payroll', 'amount' => 50],
            ],
            'expenses' => [],
        ]);

        $response->assertRedirect(route('admin.cash-daily-position.show', ['date' => '2026-10-05']))
            ->assertSessionHas('status');

        // Remarks are saved on the cash_received (addition) lines only.
        $this->assertSame('October bookkeeping payment', CashDailyLine::where('label', 'Villabroza - Bookkeeping 4th of Oct')->value('notes'));
        $this->assertSame('Bookkeeping collection', CashDailyLine::where('label', 'Andres - Bookkeeping 1 and 2 of Oct')->value('notes'));
        $this->assertSame('Payroll notes that stay private.', $salary->refresh()->notes);
        $this->assertSame(1000.00, (float) $position->fresh()->totalAdditions());

        // Remarks reload when reopening the report in edit mode.
        $response = $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.create', ['date' => '2026-10-05']));

        $response->assertOk()
            ->assertSee('CASH RECEIVED')
            ->assertSee('name="additions[1][notes]"', false)
            ->assertSee('October bookkeeping payment')
            ->assertSee('Bookkeeping collection')
            ->assertSee('placeholder="Enter remarks..."', false)
            ->assertDontSee('salaries[0][notes]', false)
            ->assertDontSee('expenses[0][notes]', false);

        $this->assertSame(2, preg_match_all('/name="additions\[\d+\]\[notes\]"/', $response->getContent(), $m),
            'Every cash-received row must render a Remarks input.');
        $response->assertSee('Villabroza - Bookkeeping 4th of Oct')
            ->assertSee('Andres - Bookkeeping 1 and 2 of Oct');

        // Remarks are preserved when an existing entry is edited.
        $this->actingAs($admin)->post(route('admin.cash-daily-position.store-daily'), [
            'position_id' => $position->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'actual_cash' => null,
            'additions' => [
                ['label' => 'Villabroza - Bookkeeping 4th of Oct', 'notes' => 'Updated October bookkeeping payment', 'amount' => 400],
                ['label' => 'Andres - Bookkeeping 1 and 2 of Oct', 'notes' => null, 'amount' => 600],
            ],
            'salaries' => [['id' => $salary->id, 'label' => 'Payroll', 'amount' => 50]],
            'expenses' => [],
        ]);

        $this->assertSame('Updated October bookkeeping payment', CashDailyLine::where('label', 'Villabroza - Bookkeeping 4th of Oct')->value('notes'));
        $this->assertNull(CashDailyLine::where('label', 'Andres - Bookkeeping 1 and 2 of Oct')->value('notes'));
        $this->assertSame('Payroll notes that stay private.', CashDailyLine::whereKey($salary->id)->value('notes'));

        // A saved report keeps remarks in its printed detail view.
        $this->actingAs($admin)->post(route('admin.cash-daily-position.reports.store'), [
            'client_id' => $client->id,
            'period_type' => 'month',
            'year' => 2026,
            'month' => 10,
        ])->assertSessionHas('status');

        $report = CashDailyReport::firstOrFail();
        $this->assertSame(1000.00, (float) $report->total_collected);
        $this->assertSame(50.00, (float) $report->total_less);
        $this->assertSame(950.00, (float) ($report->beginning_balance ?? 0) + 1000.00 - 50.00);

        $line = CashDailyReportLine::where('label', 'Villabroza - Bookkeeping 4th of Oct')->firstOrFail();
        $this->assertSame('Updated October bookkeeping payment', $line->notes);

        $index = $this->actingAs($admin)->get(route('admin.cash-daily-position.index'));
        $index->assertOk()
            ->assertSee('Remarks: Updated October bookkeeping payment')
            ->assertDontSee('Remarks: Payroll notes that stay private.');
    }

    public function test_receipt_upload_produces_exactly_one_success_notification(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-18');
        $show = route('admin.cash-daily-position.show', ['date' => '2026-11-18']);

        $response = $this->actingAs($admin)
            ->from($show)
            ->followingRedirects()
            ->post(route('admin.cash-daily-position.receipts.store', ['position' => $position->id]), [
                'receipts' => [UploadedFile::fake()->image('one.jpg', 40, 40)],
            ]);

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, '1 receipt(s) uploaded.'));
        $this->assertSame(1, substr_count($html, 'alert alert-success'));

        // Old notifications do not reappear after refreshing.
        $this->actingAs($admin)->get($show)->assertDontSee('1 receipt(s) uploaded.');

        // Multiple uploads produce exactly one summary notification.
        $response = $this->actingAs($admin)
            ->from($show)
            ->followingRedirects()
            ->post(route('admin.cash-daily-position.receipts.store', ['position' => $position->id]), [
                'receipts' => [
                    UploadedFile::fake()->image('two.png', 40, 40),
                    UploadedFile::fake()->image('three.jpg', 40, 40),
                ],
            ]);

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, '2 receipt(s) uploaded.'));
        $this->assertSame(1, substr_count($html, 'alert alert-success'));

        $this->trackFiles($position);
    }

    public function test_invalid_receipt_upload_produces_exactly_one_error_notification(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-19');
        $show = route('admin.cash-daily-position.show', ['date' => '2026-11-19']);

        $response = $this->actingAs($admin)
            ->from($show)
            ->followingRedirects()
            ->post(route('admin.cash-daily-position.receipts.store', ['position' => $position->id]), [
                'receipts' => [UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload')],
            ]);

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(0, $position->receipts()->count());
        $this->assertSame(1, substr_count($html, 'alert alert-error'));
    }

    public function test_deleting_one_receipt_produces_exactly_one_notification(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-20');
        $show = route('admin.cash-daily-position.show', ['date' => '2026-11-20']);

        $this->actingAs($admin)->post(
            route('admin.cash-daily-position.receipts.store', ['position' => $position->id]),
            ['receipts' => [UploadedFile::fake()->image('keep.jpg', 40, 40)]],
        );

        $response = $this->actingAs($admin)
            ->from($show)
            ->followingRedirects()
            ->delete(route('admin.cash-daily-position.receipts.destroy', ['receipt' => $position->receipts()->first()->id]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Receipt deleted.'));
        $this->assertSame(1, substr_count($html, 'alert alert-success'));

        $this->actingAs($admin)->get($show)->assertDontSee('Receipt deleted.');
    }

    public function test_summary_rows_use_consistent_structure(): void
    {
        $admin = $this->admin();
        $position = $this->position('2026-11-21', null, 100);

        $response = $this->actingAs($admin)
            ->get(route('admin.cash-daily-position.show', ['date' => '2026-11-21']));

        $response->assertOk()
            ->assertSee('<table class="cdp-receipt-table cdp-summary-table" id="summaryTable">', false)
            ->assertSee('<thead><tr><th>Particulars</th><th class="num">Amount</th></tr></thead>', false)
            ->assertSee('<td>Beginning Balance</td><td class="num" id="sumBeg">₱100.00</td>', false)
            ->assertSee('<td>ENDING BALANCE</td><td class="num" id="sumEnd">₱100.00</td>', false)
            ->assertSee('<td>OVERAGE / (SHORTAGE)</td><td class="num" id="sumOS">₱0.00</td>', false)
            ->assertDontSee('<th>ENDING BALANCE</th>', false)
            ->assertDontSee('<th>OVERAGE / (SHORTAGE)</th>', false);
    }

    public function test_receipt_actions_require_authentication(): void
    {
        $position = $this->position('2026-11-17');

        $this->post(route('admin.cash-daily-position.receipts.store', ['position' => $position->id]), [
            'receipts' => [UploadedFile::fake()->image('guest.jpg', 20, 20)],
        ])->assertRedirect(route('login'));

        $this->assertSame(0, $position->receipts()->count());
    }
}
