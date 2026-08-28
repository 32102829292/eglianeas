<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingDraftTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::create([
            'name' => 'Draft Client',
            'email' => 'draft'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
        ]);
    }

    private function paidBilling(User $client, int $quarter, int $year = 2026): Billing
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

    public function test_paying_q1_creates_q2_draft_with_copied_line_items(): void
    {
        $client = $this->client();
        $paid = $this->paidBilling($client, 1);

        $draft = Billing::makeNextDraft($paid);

        $this->assertNotNull($draft);
        $this->assertSame(2, $draft->quarter);
        $this->assertSame(2026, $draft->year);
        $this->assertSame($client->id, $draft->client_id);
        $this->assertTrue($draft->isDraft());
        $this->assertSame($paid->cash_in, $draft->cash_in);
        // Line item template copied and total recomputed.
        $this->assertSame(1, $draft->lineItems()->count());
        $this->assertSame(600.0, (float) $draft->total);
    }

    public function test_paying_q4_creates_no_draft(): void
    {
        $client = $this->client();
        $paid = $this->paidBilling($client, 4);

        $this->assertNull(Billing::makeNextDraft($paid));
        $this->assertSame(0, Billing::where('client_id', $client->id)->where('year', 2026)->where('quarter', 5)->count());
    }

    public function test_no_duplicate_draft_when_next_quarter_already_exists(): void
    {
        $client = $this->client();
        $paid = $this->paidBilling($client, 1);

        $first = Billing::makeNextDraft($paid);
        $this->assertNotNull($first);

        $second = Billing::makeNextDraft($paid);
        $this->assertNull($second);
        $this->assertSame(1, Billing::where('client_id', $client->id)->where('year', 2026)->where('quarter', 2)->count());
    }

    public function test_next_quarter_for_skips_occupied_and_draft_slots(): void
    {
        $client = $this->client();

        // Q1 active billing exists -> next should be Q2.
        $this->paidBilling($client, 1);
        $this->assertSame(2, Billing::nextQuarterFor($client->id, 2026));

        // A draft occupying Q2 should make the default skip to Q3.
        $draft = new Billing;
        $draft->client_id = $client->id;
        $draft->quarter = 2;
        $draft->year = 2026;
        $draft->period_label = '2ND QUARTER 2026 BILLING';
        $draft->status = Billing::STATUS_DRAFT;
        $draft->save();

        $this->assertSame(3, Billing::nextQuarterFor($client->id, 2026));
    }
}
