<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\BirFormStatus;
use App\Models\BirFormType;
use App\Models\ClientCompany;
use App\Models\FeeRate;
use App\Models\User;
use App\Support\BillingFrequency;
use App\Support\BillingSummaryMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The billing worksheet: every service is an explicit include/exclude decision.
 *
 * The page used to render one amount field per service with nothing indicating
 * whether the charge belonged to the current statement, so an annual return or
 * an attachment could ride along unnoticed. These tests pin the three things
 * that make a charge intentional:
 *
 *   1. frequency tells the admin when a service is actually due;
 *   2. only recurring services may be pre-ticked, so annual/one-time/as-needed
 *      rows must be opted into by hand and are recorded as manual inclusions;
 *   3. the generated summary lists only categories the period really charged,
 *      and never recomputes a historical statement from today's prices.
 */
class BillingWorksheetSelectionTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'Worksheet Admin');
    }

    private function client(): User
    {
        return $this->internal(User::ROLE_CLIENT, 'Worksheet Client');
    }

    private function company(User $client): ClientCompany
    {
        return ClientCompany::create([
            'client_id' => $client->id,
            'branch_number' => 1,
            'company_code' => $client->client_code.'-01',
            'company_name' => 'Worksheet Co',
        ]);
    }

    private function applicable(User $client, array $formTypes): void
    {
        foreach ($formTypes as $type) {
            BirFormStatus::create([
                'client_id' => $client->id,
                'client_company_id' => $client->companies()->value('id'),
                'form_type' => $type,
                'status' => BirFormStatus::STATUS_NOT_FILED,
                'applicable' => true,
            ]);
        }
    }

    private function payload(User $client, array $lineItems): array
    {
        return [
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'line_items' => $lineItems,
        ];
    }

    // ------------------------------------------------------------------
    // Frequency
    // ------------------------------------------------------------------

    public function test_bir_form_codes_map_to_their_real_filing_frequency(): void
    {
        // The annual income tax returns must never be treated as quarterly.
        $this->assertSame(BillingFrequency::ANNUAL, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, '1701'));
        $this->assertSame(BillingFrequency::ANNUAL, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, '1702'));

        $this->assertSame(BillingFrequency::QUARTERLY, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, '1701Q'));
        $this->assertSame(BillingFrequency::QUARTERLY, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, '2551Q'));
        $this->assertSame(BillingFrequency::MONTHLY, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, '1601C'));
        $this->assertSame(BillingFrequency::MONTHLY, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, '0619E'));
        $this->assertSame(BillingFrequency::MONTHLY, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, '0619F'));
    }

    public function test_only_recurring_frequencies_may_be_pre_ticked(): void
    {
        $this->assertTrue(BillingFrequency::isPreTicked(BillingFrequency::MONTHLY));
        $this->assertTrue(BillingFrequency::isPreTicked(BillingFrequency::QUARTERLY));

        // These are the ones that must never be auto-included.
        $this->assertFalse(BillingFrequency::isPreTicked(BillingFrequency::ANNUAL));
        $this->assertFalse(BillingFrequency::isPreTicked(BillingFrequency::ONE_TIME));
        $this->assertFalse(BillingFrequency::isPreTicked(BillingFrequency::AS_NEEDED));
        $this->assertFalse(BillingFrequency::isPreTicked(null));
    }

    public function test_an_unselected_service_explains_why_it_is_empty(): void
    {
        $this->assertSame('Not scheduled for this quarter', BillingFrequency::notScheduledNote(BillingFrequency::ANNUAL));
        $this->assertNotNull(BillingFrequency::notScheduledNote(BillingFrequency::ONE_TIME));
        $this->assertNotNull(BillingFrequency::notScheduledNote(BillingFrequency::AS_NEEDED));
        // A recurring service is due, so it has no "not scheduled" explanation.
        $this->assertNull(BillingFrequency::notScheduledNote(BillingFrequency::QUARTERLY));
    }

    public function test_cash_in_is_treated_as_a_manual_item_not_a_recurring_charge(): void
    {
        // Cash In is a bir_remittance line with no form type.
        $this->assertSame(BillingFrequency::AS_NEEDED, BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, null));
        $this->assertFalse(BillingFrequency::isPreTicked(BillingFrequency::AS_NEEDED));
    }

    public function test_every_seeded_bir_form_type_has_a_known_frequency(): void
    {
        // Guards against a new BIR form being added to the master list without a
        // frequency, which would silently render an unknown badge.
        $codes = BirFormType::query()->pluck('code')->all();
        $this->assertNotEmpty($codes, 'Expected the BIR form type master list to be seeded.');

        foreach ($codes as $code) {
            $this->assertTrue(
                BillingFrequency::isKnown(BillingFrequency::forLineItem(BillingLineItem::CATEGORY_BIR_REMITTANCE, $code)),
                "BIR form {$code} has no known billing frequency."
            );
        }
    }

    // ------------------------------------------------------------------
    // What actually gets stored
    // ------------------------------------------------------------------

    public function test_saving_a_statement_snapshots_frequency_and_manual_inclusion(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701', '1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            // Annual return the admin deliberately pulled into this quarter.
            ['category' => 'bir_remittance', 'form_type' => '1701', 'label' => '', 'amount' => 2500, 'manual_include' => '1'],
            // Ordinary quarterly remittance, included on schedule.
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
            ['category' => 'bookkeeping_fee', 'form_type' => '', 'label' => 'Bookkeeping', 'amount' => 1500],
        ]))->assertRedirect(route('admin.billing.index'));

        $annual = BillingLineItem::where('form_type', '1701')->sole();
        $this->assertSame(BillingFrequency::ANNUAL, $annual->frequency);
        $this->assertTrue((bool) $annual->manual_include, 'A deliberately included annual item must be recorded as manual.');
        $this->assertSame('Annual', $annual->frequencyLabel());

        $quarterly = BillingLineItem::where('form_type', '1701Q')->sole();
        $this->assertSame(BillingFrequency::QUARTERLY, $quarterly->frequency);
        $this->assertFalse((bool) $quarterly->manual_include);

        $bookkeeping = BillingLineItem::where('category', 'bookkeeping_fee')->sole();
        $this->assertSame(BillingFrequency::MONTHLY, $bookkeeping->frequency);
    }

    public function test_an_excluded_service_is_never_persisted(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701', '1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
            // Unchecked rows submit no amount, exactly like a blank field.
            ['category' => 'bir_remittance', 'form_type' => '1701', 'label' => '', 'amount' => ''],
            ['category' => 'inventory_list', 'form_type' => '', 'label' => 'Inventory List (Notarized)', 'amount' => ''],
        ]))->assertRedirect(route('admin.billing.index'));

        $this->assertDatabaseMissing('billing_line_items', ['form_type' => '1701']);
        $this->assertDatabaseMissing('billing_line_items', ['category' => 'inventory_list']);
        $this->assertSame(320.0, (float) Billing::sole()->total);
    }

    public function test_a_custom_item_keeps_its_description_frequency_and_notes(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
            [
                'category' => BillingLineItem::CATEGORY_CUSTOM,
                'form_type' => '',
                'label' => 'Annual BIR Registration',
                'amount' => 500,
                'frequency' => BillingFrequency::ANNUAL,
                'notes' => 'Registration renewal',
            ],
        ]))->assertRedirect(route('admin.billing.index'));

        $custom = BillingLineItem::where('category', BillingLineItem::CATEGORY_CUSTOM)->sole();
        $this->assertSame('Annual BIR Registration', $custom->label);
        $this->assertSame(BillingFrequency::ANNUAL, $custom->frequency);
        $this->assertSame('Registration renewal', $custom->notes);
        $this->assertSame(820.0, (float) Billing::sole()->total);
    }

    /**
     * Regression guard for "adding item #2 drops item #1".
     *
     * Each custom row the modal appends gets its own line_items[N] index and is
     * saved independently, so adding a second or third item must never replace
     * the ones already entered. This drives the three-item flow all the way
     * through a save and checks every label survives and the statement total is
     * the sum of all of them.
     */
    public function test_multiple_custom_items_are_all_retained_and_totalled(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
            ['category' => BillingLineItem::CATEGORY_CUSTOM, 'form_type' => '', 'label' => '3641', 'amount' => 20, 'frequency' => BillingFrequency::ONE_TIME, 'notes' => 'Registration renewal'],
            ['category' => BillingLineItem::CATEGORY_CUSTOM, 'form_type' => '', 'label' => 'SEC Filing', 'amount' => 750, 'frequency' => BillingFrequency::ONE_TIME],
            ['category' => BillingLineItem::CATEGORY_CUSTOM, 'form_type' => '', 'label' => 'Notarization', 'amount' => 150, 'frequency' => BillingFrequency::ONE_TIME],
        ]))->assertRedirect(route('admin.billing.index'));

        $custom = BillingLineItem::where('category', BillingLineItem::CATEGORY_CUSTOM)->get();
        $this->assertCount(3, $custom, 'Adding a second or third custom item must not drop the earlier ones.');
        $this->assertEqualsCanonicalizing(
            ['3641', 'SEC Filing', 'Notarization'],
            $custom->pluck('label')->all()
        );
        // 320 remittance + 20 + 750 + 150 custom items.
        $this->assertSame(1240.0, (float) Billing::sole()->total);
    }

    /**
     * Regression guard for the click-does-nothing half of the same bug.
     *
     * The Add Item button shipped as type="button" and nothing referenced it,
     * so clicking it never reached confirmCustomItem(); only an Enter keypress
     * (implicit form submission) added a row, which is why the modal "did not
     * reliably" add items. Pin the wiring the fix relies on.
     */
    public function test_the_add_billing_item_button_submits_the_form(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)
            ->get(route('admin.billing.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*type="submit"[^>]*id="confirmAddBillingItem"/',
            $html,
            'Add Item must be a submit button, otherwise clicking it never runs the form submit handler.'
        );

        // The form submit handler is the code that validates and adds the row.
        $this->assertStringContainsString("addEventListener('submit'", $html);
        $this->assertStringContainsString('confirmCustomItem', $html);

        // New rows are appended, never written over the container, so item #2
        // can never replace item #1.
        $this->assertStringContainsString('customContainer.appendChild(buildCustomItemRow(', $html);
    }

    public function test_editing_a_custom_item_amount_does_not_touch_the_master_fee_rate(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $rate = FeeRate::create([
            'label' => 'Bookkeeping',
            'amount' => 1500,
            'category' => FeeRate::CATEGORY_BOOKKEEPING_FEE,
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
            ['category' => 'bookkeeping_fee', 'form_type' => '', 'label' => 'Bookkeeping', 'amount' => 1300, 'fee_rate_id' => $rate->id],
        ]))->assertRedirect(route('admin.billing.index'));

        // The statement charges the adjusted figure...
        $this->assertSame(1620.0, (float) Billing::sole()->total);
        $this->assertSame(1300.0, (float) BillingLineItem::where('category', 'bookkeeping_fee')->sole()->amount);

        // ...while the master default price is untouched for future statements.
        $this->assertSame(1500.0, (float) $rate->fresh()->amount);
    }

    public function test_editing_a_paid_statement_is_still_rejected(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
        ]))->assertRedirect(route('admin.billing.index'));

        $billing = Billing::sole();
        $billing->status = Billing::STATUS_PAID;
        $billing->paid_at = now();
        $billing->save();

        $this->actingAs($admin)->put(route('admin.billing.update', $billing), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 999],
        ]))->assertForbidden();

        $this->assertSame(320.0, (float) $billing->fresh()->total);
    }

    public function test_the_edit_page_of_a_paid_statement_is_rendered_read_only(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
        ]));

        $billing = Billing::sole();
        $billing->status = Billing::STATUS_PAID;
        $billing->paid_at = now();
        $billing->save();

        $this->actingAs($admin)->get(route('admin.billing.edit', $billing))
            ->assertOk()
            ->assertSee('This billing statement is finalized and cannot be edited.', false)
            // The submit control is replaced by a read-only link.
            ->assertSee('View Statement')
            ->assertDontSee('+ Add Custom Billing Item');
    }

    // ------------------------------------------------------------------
    // Historical integrity
    // ------------------------------------------------------------------

    public function test_a_saved_statement_is_not_recalculated_from_later_master_prices(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
            ['category' => 'bookkeeping_fee', 'form_type' => '', 'label' => 'Bookkeeping', 'amount' => 1500],
        ]));

        $billing = Billing::sole();
        $this->assertSame(1820.0, (float) $billing->total);

        // Someone raises the bookkeeping rate afterwards.
        FeeRate::create([
            'label' => 'Bookkeeping', 'amount' => 1800,
            'category' => FeeRate::CATEGORY_BOOKKEEPING_FEE, 'sort_order' => 1, 'active' => true,
        ]);

        $billing->refresh();
        $this->assertSame(1820.0, (float) $billing->total);
        $this->assertSame(1500.0, (float) BillingLineItem::where('category', 'bookkeeping_fee')->sole()->amount);

        // And the generated summary still reports the original figure.
        $matrix = BillingSummaryMatrix::make(collect([$billing->fresh('lineItems')]));
        $row = $matrix->rows()[0];
        $this->assertSame(1500.0, (float) $row['bookkeeping_fee']);
        $this->assertSame(1820.0, (float) $row['grand_total']);
    }

    // ------------------------------------------------------------------
    // Summary shape — only what was actually charged
    // ------------------------------------------------------------------

    public function test_the_summary_omits_categories_the_period_never_charged(): void
    {
        $client = $this->client();
        $billing = Billing::create([
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'period_label' => '2ND QUARTER 2026 BILLING',
            'status' => Billing::STATUS_UNPAID,
            'total' => 320,
        ]);

        BillingLineItem::create([
            'billing_id' => $billing->id,
            'category' => BillingLineItem::CATEGORY_BIR_REMITTANCE,
            'form_type' => '1701Q',
            'label' => '1701Q Remittance',
            'amount' => 320,
            'frequency' => BillingFrequency::QUARTERLY,
        ]);

        $matrix = BillingSummaryMatrix::make(collect([$billing->fresh('lineItems')]));
        $keys = array_column($matrix->columns(), 'key');

        $this->assertContains('bir:1701Q', $keys);
        $this->assertContains('fee:1701Q', $keys);

        // Nothing was charged for these, so they must not appear as ₱0.00 columns.
        foreach (['bookkeeping_fee', 'post_closing_tb', 'inventory_list', 'other_attachment', 'data_entry', 'cash_in', 'custom'] as $unused) {
            $this->assertNotContains($unused, $keys, "Unused category [{$unused}] must not produce a summary column.");
        }
    }

    public function test_the_summary_still_lists_every_category_that_was_charged(): void
    {
        $client = $this->client();
        $billing = Billing::create([
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'period_label' => '2ND QUARTER 2026 BILLING',
            'status' => Billing::STATUS_UNPAID,
            'total' => 3000,
        ]);

        $rows = [
            [BillingLineItem::CATEGORY_BIR_REMITTANCE, '1701Q', 320, BillingFrequency::QUARTERLY],
            [BillingLineItem::CATEGORY_BOOKKEEPING_FEE, null, 1500, BillingFrequency::MONTHLY],
            [BillingLineItem::CATEGORY_POST_CLOSING_TB, null, 500, BillingFrequency::AS_NEEDED],
            [BillingLineItem::CATEGORY_INVENTORY_LIST, null, 200, BillingFrequency::AS_NEEDED],
            [BillingLineItem::CATEGORY_OTHER_ATTACHMENT, null, 100, BillingFrequency::AS_NEEDED],
            [BillingLineItem::CATEGORY_DATA_ENTRY, null, 380, BillingFrequency::AS_NEEDED],
            [BillingLineItem::CATEGORY_CUSTOM, null, 500, BillingFrequency::ONE_TIME],
        ];

        foreach ($rows as [$category, $formType, $amount, $frequency]) {
            BillingLineItem::create([
                'billing_id' => $billing->id,
                'category' => $category,
                'form_type' => $formType,
                'label' => $formType ?: $category,
                'amount' => $amount,
                'frequency' => $frequency,
            ]);
        }

        // A Cash In offset, which is a bir_remittance line without a form type.
        BillingLineItem::create([
            'billing_id' => $billing->id,
            'category' => BillingLineItem::CATEGORY_BIR_REMITTANCE,
            'form_type' => null,
            'label' => 'Cash In',
            'amount' => 250,
            'frequency' => BillingFrequency::AS_NEEDED,
        ]);

        $matrix = BillingSummaryMatrix::make(collect([$billing->fresh('lineItems')]));
        $keys = array_column($matrix->columns(), 'key');

        foreach (['bookkeeping_fee', 'post_closing_tb', 'inventory_list', 'other_attachment', 'data_entry', 'custom', 'cash_in'] as $used) {
            $this->assertContains($used, $keys, "Charged category [{$used}] must appear in the summary.");
        }

        $row = $matrix->rows()[0];
        $this->assertSame(320.0, (float) $row['bir:1701Q']);
        $this->assertSame(250.0, (float) $row['cash_in']);
        $this->assertSame(500.0, (float) $row['custom']);
        // Remittance subtotal excludes Cash In, matching the existing workbook
        // arithmetic; the grand total still reconciles with the whole row.
        //   remittance 320 + fee 3430 (250 cash-in, 1500, 500, 200, 100, 380, 500)
        $this->assertSame(320.0, (float) $row['remittance_subtotal']);
        $this->assertSame(3430.0, (float) $row['fee_subtotal']);
        $this->assertSame(3750.0, (float) $row['grand_total']);
    }

    /**
     * The worksheet posts Cash In with an empty form_type; it is stored as NULL.
     *
     * This is a stored-invariant test, not a bug fix. BillingSummaryMatrix reads
     * line items as an Eloquent Collection, where Collection::whereNull compares
     * with `==` and so already matched the empty string. The query-builder path
     * (BillingLineItem::scopeCashIn / Billing::cashInItem) uses SQL `IS NULL`
     * and would not have matched ''. Those callers are currently unused, so the
     * normalisation simply keeps the stored value consistent for both lookups
     * instead of relying on PHP's loose comparison.
     */
    public function test_cash_in_submitted_from_the_form_is_stored_with_a_null_form_type(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $this->actingAs($admin)->post(route('admin.billing.store'), $this->payload($client, [
            ['category' => 'bir_remittance', 'form_type' => '1701Q', 'label' => '', 'amount' => 320],
            ['category' => 'bir_remittance', 'form_type' => '', 'label' => 'Cash In', 'amount' => 250],
        ]))->assertRedirect(route('admin.billing.index'));

        $cashIn = BillingLineItem::where('label', 'Cash In')->sole();
        $this->assertNull($cashIn->form_type, 'Cash In must be stored with a NULL form type.');
        $this->assertSame(BillingFrequency::AS_NEEDED, $cashIn->frequency);

        $billing = Billing::sole();
        $this->assertSame(570.0, (float) $billing->total);

        $matrix = BillingSummaryMatrix::make(collect([$billing->fresh('lineItems')]));
        $keys = array_column($matrix->columns(), 'key');
        $this->assertContains('cash_in', $keys);

        $row = $matrix->rows()[0];
        $this->assertSame(250.0, (float) $row['cash_in']);
        $this->assertSame(320.0, (float) $row['remittance_subtotal']);
        $this->assertSame(570.0, (float) $row['grand_total'], 'The Cash In charge must be represented in the summary, not just the stored total.');
    }

    // ------------------------------------------------------------------
    // Page surface
    // ------------------------------------------------------------------

    public function test_the_applicable_forms_endpoint_also_reports_every_active_form(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->company($client);
        $this->applicable($client, ['1701Q']);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.billing.applicableForms', ['client_id' => $client->id]))
            ->assertOk();

        $forms = $response->json('forms');
        $allForms = $response->json('all_forms');

        $this->assertSame(['1701Q'], $forms);

        // The billing page needs the full list so it can render the forms the
        // client is not registered for as "Not applicable" instead of hiding
        // them and leaving the admin unsure whether they were forgotten.
        $this->assertContains('1701', $allForms);
        $this->assertContains('1701Q', $allForms);
        $this->assertNotEmpty(array_diff($allForms, $forms), 'Expected at least one form the client is not registered for.');
    }

    public function test_the_create_page_ships_the_selection_worksheet(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)
            ->get(route('admin.billing.create'))
            ->assertOk()
            ->getContent();

        // Static scaffolding.
        $this->assertStringContainsString('Billing Items', $html);
        $this->assertStringContainsString('id="lineItemsContainer"', $html);
        $this->assertStringContainsString('+ Add Custom Billing Item', $html);
        $this->assertStringContainsString('id="addBillingItemModal"', $html);
        $this->assertStringContainsString('Billing Summary', $html);

        // The Add Billing Item modal must collect description, amount,
        // frequency and notes.
        foreach (['customItemDescription', 'customItemAmount', 'customItemFrequency', 'customItemNotes'] as $field) {
            $this->assertStringContainsString('id="'.$field.'"', $html, "The custom item modal is missing [{$field}].");
        }

        // The per-row selection + summary wiring the whole design depends on.
        foreach (['data-bill-check', 'setRowSelected', 'computeTotals', 'data-bill-manual', 'data-bill-section-count'] as $marker) {
            $this->assertStringContainsString($marker, $html, "The worksheet script is missing [{$marker}].");
        }

        // One shared index counter for every row, including custom items.
        $this->assertStringContainsString('itemIndex++', $html);
        $this->assertStringNotContainsString('customIndex', $html, 'Custom items must not use a separate index counter.');
    }

    public function test_the_frequency_maps_reach_the_browser_so_badges_render(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get(route('admin.billing.create'))->assertOk()->getContent();

        $this->assertStringContainsString('1701Q', $html);
        $this->assertStringContainsString('Not scheduled for this quarter', $html);
        $this->assertStringContainsString('Manually included', $html);
        $this->assertStringContainsString('Not applicable', $html);
    }

    /**
     * The frequency tables exist twice — once in PHP and once as JSON handed to
     * the page — because the rows are built in the browser. This pins the two
     * copies together so a change to BillingFrequency can never leave the page
     * ticking an annual service that PHP considers quarterly.
     */
    public function test_the_browser_copy_of_the_frequency_map_matches_php(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get(route('admin.billing.create'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/var FREQ = (\{.*?\});/s', $html, $m), 'Could not find the FREQ map on the page.');
        $jsMap = json_decode($m[1], true);

        $this->assertIsArray($jsMap, 'The FREQ map must be valid JSON.');
        $this->assertSame(BillingFrequency::FORM_FREQUENCIES, $jsMap['forms']);
        $this->assertSame(BillingFrequency::CATEGORY_FREQUENCIES, $jsMap['categories']);
        $this->assertSame(BillingFrequency::RECURRING, $jsMap['recurring']);
        $this->assertSame(BillingFrequency::LABELS, $jsMap['labels']);

        // The pre-tick decision the browser makes must match the PHP rule:
        // only monthly and quarterly rows may start included.
        foreach ($jsMap['recurring'] as $recurring) {
            $this->assertTrue(BillingFrequency::isPreTicked($recurring));
        }
        foreach (['annual', 'one_time', 'as_needed'] as $optional) {
            $this->assertNotContains($optional, $jsMap['recurring']);
            $this->assertFalse(BillingFrequency::isPreTicked($optional));
        }

        // Every label the page can render must exist, or a badge would show a
        // blank string.
        foreach (array_merge(array_values($jsMap['forms']), array_values($jsMap['categories'])) as $freq) {
            $this->assertArrayHasKey($freq, $jsMap['labels'], "Frequency [{$freq}] has no label for the badge.");
        }
    }
}