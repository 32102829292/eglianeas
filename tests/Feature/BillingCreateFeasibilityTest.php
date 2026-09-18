<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\BirFormStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Billing creation feasibility: the Create Billing Statement flow must refuse to
 * create a statement when (a) the client has no applicable BIR forms selected on
 * the BIR Forms page, or (b) no line item carries an amount. These are the
 * server-side mirrors of the client-side pre-confirm guard.
 */
class BillingCreateFeasibilityTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'Billing Feasibility Admin');
    }

    private function client(): User
    {
        return $this->internal(User::ROLE_CLIENT, 'Billing Feasibility Client');
    }

    private function payload(User $client, array $lineItems = []): array
    {
        return [
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'due_date' => now()->addDays(10)->toDateString(),
            'cash_in' => 0,
            'line_items' => $lineItems,
        ];
    }

    private function bookkeepingLineItem(float $amount = 2500): array
    {
        return [
            'category' => BillingLineItem::CATEGORY_BOOKKEEPING_FEE,
            'form_type' => '',
            'month' => '',
            'label' => 'Bookkeeping',
            'amount' => $amount,
            'fee_rate_id' => null,
        ];
    }

    public function test_creation_is_blocked_when_client_has_no_applicable_bir_forms(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $response = $this->actingAs($admin)
            ->post(route('admin.billing.store'), $this->payload($client, [$this->bookkeepingLineItem()]));

        $response->assertRedirect()->assertSessionHasErrors('client_id');

        $this->assertDatabaseMissing('billings', ['client_id' => $client->id]);
    }

    public function test_creation_succeeds_when_client_has_an_applicable_bir_form(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        BirFormStatus::create([
            'client_id' => $client->id,
            'form_type' => BirFormStatus::FORM_TYPES[0],
            'applicable' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.billing.store'), $this->payload($client, [$this->bookkeepingLineItem()]));

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('billings', [
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'status' => Billing::STATUS_UNPAID,
        ]);
        $this->assertDatabaseHas('billing_line_items', [
            'billing_id' => Billing::where('client_id', $client->id)->value('id'),
            'category' => BillingLineItem::CATEGORY_BOOKKEEPING_FEE,
            'amount' => 2500,
        ]);
    }

    public function test_creation_is_blocked_without_any_line_item_amounts(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        BirFormStatus::create([
            'client_id' => $client->id,
            'form_type' => BirFormStatus::FORM_TYPES[0],
            'applicable' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.billing.store'), $this->payload($client, []));

        $response->assertRedirect()->assertSessionHasErrors('line_items');
        $this->assertDatabaseMissing('billings', ['client_id' => $client->id]);
    }

    public function test_zero_amount_line_items_still_block_creation(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        BirFormStatus::create([
            'client_id' => $client->id,
            'form_type' => BirFormStatus::FORM_TYPES[0],
            'applicable' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.billing.store'), $this->payload($client, [$this->bookkeepingLineItem(0)]));

        $response->assertRedirect()->assertSessionHasErrors('line_items');
        $this->assertDatabaseMissing('billings', ['client_id' => $client->id]);
    }

    public function test_duplicate_period_is_still_rejected_after_feasibility(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        BirFormStatus::create([
            'client_id' => $client->id,
            'form_type' => BirFormStatus::FORM_TYPES[0],
            'applicable' => true,
        ]);
        Billing::create([
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'period_label' => '2ND QUARTER 2026 BILLING',
            'status' => Billing::STATUS_UNPAID,
            'total' => 2500,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.billing.store'), $this->payload($client, [$this->bookkeepingLineItem()]));

        $response->assertRedirect()->assertSessionHasErrors('client_id');
        $this->assertSame(1, Billing::where('client_id', $client->id)->count());
    }

    public function test_bir_forms_page_deep_link_filter_isolates_the_client(): void
    {
        $admin = $this->admin();
        $clientA = $this->client();
        $clientB = $this->client();

        $response = $this->actingAs($admin)
            ->get(route('admin.bir-forms.index', ['client_id' => $clientA->id]));

        $response->assertOk()
            ->assertSee('client-'.$clientA->id)
            ->assertDontSee('client-'.$clientB->id);
    }

    public function test_billing_index_marks_client_without_bir_forms_with_add_action(): void
    {
        $admin = $this->admin();
        $withForms = $this->client();
        BirFormStatus::create([
            'client_id' => $withForms->id,
            'form_type' => BirFormStatus::FORM_TYPES[0],
            'applicable' => true,
        ]);
        $withoutForms = $this->client();

        $html = $this->actingAs($admin)
            ->get(route('admin.billing.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('admin.bir-forms.index', ['client_id' => $withoutForms->id]),
            $html
        );
        $this->assertStringContainsString('>Add BIR Forms</a>', $html);
        $this->assertStringContainsString('BIR Forms Ready', $html);
        $withLink = route('admin.bir-forms.index', ['client_id' => $withForms->id]);
        $this->assertStringNotContainsString('href="'.$withLink.'"', $html);
    }

    public function test_billing_index_shows_bir_forms_ready_for_client_with_forms(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        BirFormStatus::create([
            'client_id' => $client->id,
            'form_type' => BirFormStatus::FORM_TYPES[0],
            'applicable' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.billing.index'));

        $response->assertOk()->assertSee('BIR Forms Ready');
        $this->assertStringNotContainsString(
            route('admin.bir-forms.index', ['client_id' => $client->id]),
            $response->getContent()
        );
    }

    public function test_bir_forms_deep_link_offers_continue_to_billing_for_the_same_client(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $response = $this->actingAs($admin)
            ->get(route('admin.bir-forms.index', ['client_id' => $client->id]));

        $response->assertOk()
            ->assertSee('Continue to Billing')
            ->assertSee(route('admin.billing.create', ['client_id' => $client->id]));
    }

    public function test_bir_forms_page_without_deep_link_hides_continue_to_billing(): void
    {
        $admin = $this->admin();
        $this->client();

        $this->actingAs($admin)
            ->get(route('admin.bir-forms.index'))
            ->assertOk()
            ->assertDontSee('Continue to Billing');
    }

    public function test_staff_billing_index_shows_bir_forms_readiness_actions(): void
    {
        $staff = $this->internal(User::ROLE_STAFF, 'Billing Feasibility Staff');
        $withForms = $this->client();
        BirFormStatus::create([
            'client_id' => $withForms->id,
            'form_type' => BirFormStatus::FORM_TYPES[0],
            'applicable' => true,
        ]);
        $withoutForms = $this->client();

        $html = $this->actingAs($staff)
            ->get(route('admin.billing.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('BIR Forms Ready', $html);
        $this->assertStringContainsString(
            route('admin.bir-forms.index', ['client_id' => $withoutForms->id]),
            $html
        );
    }
}