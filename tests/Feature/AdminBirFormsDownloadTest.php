<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\BirFormStatus;
use App\Models\BirFormType;
use App\Models\ClientCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Download BIR Forms Summary" control on /admin/bir-forms.
 *
 * The two export links were unreachable because the menu that holds them never
 * opened: .dropdown-menu is display:none by default and only .show reveals it,
 * and this page shipped no handler for its own [data-dropdown] toggle. These
 * tests pin both halves of that: the handler exists, and the exports behind it
 * really return files for every role the existing permissions allow.
 */
class AdminBirFormsDownloadTest extends TestCase
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

    private function company(User $client, string $label = 'BIR Download Co'): ClientCompany
    {
        return ClientCompany::create([
            'client_id' => $client->id,
            'branch_number' => 1,
            'company_code' => ($client->client_code ?: 'CC').'-01',
            'company_name' => $label,
        ]);
    }

    /** One client company with one applicable form, so exports have a row. */
    private function seedOneApplicableForm(): User
    {
        $client = $this->internal(User::ROLE_CLIENT, 'BIR Download Client');
        $company = $this->company($client);

        BirFormStatus::firstOrCreate(
            [
                'client_id' => $client->id,
                'client_company_id' => $company->id,
                'form_type' => BirFormStatus::FORM_TYPES[0],
            ],
            ['status' => BirFormStatus::STATUS_NOT_FILED, 'applicable' => true]
        );

        return $client;
    }

    public function test_the_download_control_and_both_export_links_are_present(): void
    {
        $this->seedOneApplicableForm();

        $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.index'))
            ->assertOk()
            ->assertSee('data-dropdown="bir-download-menu"', false)
            ->assertSee('id="bir-download-menu"', false)
            ->assertSee(route('admin.bir-forms.exportXlsx'), false)
            ->assertSee(route('admin.bir-forms.exportPdf'), false);
    }

    /**
     * The regression guard for the actual bug.
     *
     * The menu is display:none until .show is added, so the page must ship a
     * delegated [data-dropdown] handler. Asserting on the rendered script is the
     * only way to catch this without a browser; the export tests below then
     * prove the endpoints behind the menu work.
     */
    public function test_the_download_menu_ships_a_handler_that_reveals_it(): void
    {
        $this->seedOneApplicableForm();

        $html = $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('[data-dropdown]', $html, 'The page must handle its own [data-dropdown] toggle.');
        $this->assertStringContainsString('classList.toggle(\'show\')', $html, 'The handler must add .show so the hidden menu becomes visible.');
        $this->assertStringContainsString('getElementById', $html, 'The handler must resolve the menu the button points at.');
    }

    public function test_admin_can_download_the_xlsx_summary(): void
    {
        $this->seedOneApplicableForm();

        $response = $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.exportXlsx'));

        $response->assertOk();

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Egliane-BIR-Forms-Summary-', $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);

        $body = (string) $response->streamedContent();
        $this->assertNotSame('', $body);
        // A real .xlsx is a zip archive, so it must start with the PK signature.
        $this->assertSame("PK\x03\x04", substr($body, 0, 4), 'The XLSX response must be a real xlsx (zip) file.');
    }

    /**
     * Regression guard for ERR_INVALID_RESPONSE on the XLSX export.
     *
     * The controller hard-coded a 19-entry $colWidths list. The sheet is
     * 5 descriptive columns + one per form type + a Total column, so a 14th
     * form type produced 20 headers and $colWidths[19] hit an undefined key.
     * Laravel converts that warning to an ErrorException inside the stream
     * callback, after the 200 + xlsx headers were already flushed, so the body
     * came back empty and the browser reported ERR_INVALID_RESPONSE. Seed well
     * past the old limit to prove the width list now scales with the headers.
     */
    public function test_xlsx_export_is_valid_when_there_are_more_form_types_than_the_legacy_width_list(): void
    {
        $this->seedOneApplicableForm();

        foreach (range(1, 6) as $i) {
            BirFormType::create([
                'code' => 'XTRA'.$i,
                'name' => 'Extra Form Type '.$i,
                'active' => true,
                'sort_order' => 100 + $i,
            ]);
        }

        // 13 seeded defaults + these 6 = 19 form types -> 25 headers.
        $response = $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.exportXlsx'));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
            'The XLSX export must advertise the spreadsheet content type.'
        );

        $body = (string) $response->streamedContent();
        $this->assertNotSame('', $body, 'A column-width overflow used to leave the streamed body empty.');
        $this->assertSame("PK\x03\x04", substr($body, 0, 4), 'The XLSX must still be a valid zip archive.');
    }

    public function test_admin_can_download_the_pdf_summary(): void
    {
        $this->seedOneApplicableForm();

        $response = $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.exportPdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Egliane-BIR-Forms-Summary-', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);

        $body = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $body, 'The PDF response must be a real PDF file.');
    }

    /**
     * Regression guard for the "?" that replaced every applicable checkmark.
     *
     * The matrix paints applicable forms with U+2713, but the PDF template drew
     * them in the core Helvetica font, which has no glyph for U+2713, so DomPDF
     * substituted "?" in every filled cell. The template now renders those cells
     * in DejaVu Sans (bundled with DomPDF), so a PDF with applicable forms must
     * reference/embed a DejaVu face. Before the fix no DejaVu font was used at
     * all, so this assertion only passes once the checkmark renders correctly.
     */
    public function test_pdf_summary_embeds_a_font_with_the_checkmark_glyph(): void
    {
        $this->seedOneApplicableForm();

        $body = (string) $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.exportPdf'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'DejaVu',
            $body,
            'The PDF must embed a font that actually has the U+2713 checkmark glyph.'
        );
    }

    public function test_supervisor_can_download_both_summaries(): void
    {
        $this->seedOneApplicableForm();
        $supervisor = $this->internal(User::ROLE_SUPERVISOR, 'BIR Supervisor');

        $this->actingAs($supervisor)->get(route('admin.bir-forms.exportXlsx'))->assertOk();
        $this->actingAs($supervisor)->get(route('admin.bir-forms.exportPdf'))->assertOk();
    }

    public function test_staff_can_download_both_summaries(): void
    {
        $this->seedOneApplicableForm();
        $staff = $this->internal(User::ROLE_STAFF, 'BIR Staff');

        $this->actingAs($staff)->get(route('admin.bir-forms.exportXlsx'))->assertOk();
        $this->actingAs($staff)->get(route('admin.bir-forms.exportPdf'))->assertOk();
    }

    public function test_a_client_cannot_download_the_summaries(): void
    {
        $client = $this->seedOneApplicableForm();

        $this->actingAs($client)->get(route('admin.bir-forms.exportXlsx'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.bir-forms.exportPdf'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login_for_both_downloads(): void
    {
        $this->get(route('admin.bir-forms.exportXlsx'))->assertRedirect(route('login'));
        $this->get(route('admin.bir-forms.exportPdf'))->assertRedirect(route('login'));
    }

    public function test_an_export_is_logged_as_activity(): void
    {
        $this->seedOneApplicableForm();
        $admin = $this->internal(User::ROLE_ADMIN, 'BIR Admin');

        $this->actingAs($admin)->get(route('admin.bir-forms.exportXlsx'))->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.bir_forms_summary_exported',
        ]);
    }

    /** Existing behaviour: nothing to export for a filter that matches no client. */
    public function test_an_export_matching_no_clients_returns_404(): void
    {
        $this->seedOneApplicableForm();

        $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.exportXlsx', ['q' => 'zzz-no-such-client-zzz']))
            ->assertNotFound();

        $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.exportPdf', ['q' => 'zzz-no-such-client-zzz']))
            ->assertNotFound();
    }

    /** The board filters must still be carried into both export links. */
    public function test_the_export_links_keep_the_active_filter(): void
    {
        $this->seedOneApplicableForm();
        $company = ClientCompany::first();

        $this->actingAs($this->internal(User::ROLE_ADMIN, 'BIR Admin'))
            ->get(route('admin.bir-forms.index', ['q' => 'BIR', 'client_company_id' => $company->id]))
            ->assertOk()
            ->assertSee('client_company_id='.$company->id, false)
            ->assertSee('q=BIR', false);
    }
}