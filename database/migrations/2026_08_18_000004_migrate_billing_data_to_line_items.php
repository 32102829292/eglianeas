<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $billings = DB::table('billings')->get();
        $now = now();

        foreach ($billings as $billing) {
            $items = [];

            // BIR Remittance: 2551Q
            if ((float) ($billing->tax_2551q ?? 0) > 0) {
                $items[] = [
                    'billing_id' => $billing->id,
                    'category' => 'bir_remittance',
                    'form_type' => '2551Q',
                    'label' => '2551Q (Percentage Tax)',
                    'month' => null,
                    'amount' => $billing->tax_2551q,
                    'fee_rate_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // BIR Remittance: 1701Q
            if ((float) ($billing->tax_1701q ?? 0) > 0) {
                $items[] = [
                    'billing_id' => $billing->id,
                    'category' => 'bir_remittance',
                    'form_type' => '1701Q',
                    'label' => '1701Q (Income Tax)',
                    'month' => null,
                    'amount' => $billing->tax_1701q,
                    'fee_rate_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // BIR Remittance: Cash In
            if ((float) ($billing->cash_in ?? 0) > 0) {
                $items[] = [
                    'billing_id' => $billing->id,
                    'category' => 'bir_remittance',
                    'form_type' => null,
                    'label' => 'Cash In',
                    'month' => null,
                    'amount' => $billing->cash_in,
                    'fee_rate_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Professional Fee: 2551Q
            if ((float) ($billing->fee_2551q ?? 0) > 0) {
                $items[] = [
                    'billing_id' => $billing->id,
                    'category' => 'professional_fee',
                    'form_type' => '2551Q',
                    'label' => 'Professional Fee — 2551Q',
                    'month' => null,
                    'amount' => $billing->fee_2551q,
                    'fee_rate_id' => $this->findFeeRateId($billing->fee_2551q),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Professional Fee: 1701Q
            if ((float) ($billing->fee_1701q ?? 0) > 0) {
                $items[] = [
                    'billing_id' => $billing->id,
                    'category' => 'professional_fee',
                    'form_type' => '1701Q',
                    'label' => 'Professional Fee — 1701Q',
                    'month' => null,
                    'amount' => $billing->fee_1701q,
                    'fee_rate_id' => $this->findFeeRateId($billing->fee_1701q),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Bookkeeping Fee
            if ((float) ($billing->fee_bookkeeping ?? 0) > 0) {
                $items[] = [
                    'billing_id' => $billing->id,
                    'category' => 'bookkeeping_fee',
                    'form_type' => null,
                    'label' => 'Bookkeeping / Post-Closing Trial Balance',
                    'month' => null,
                    'amount' => $billing->fee_bookkeeping,
                    'fee_rate_id' => $this->findFeeRateId($billing->fee_bookkeeping),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($items) {
                DB::table('billing_line_items')->insert($items);
            }
        }
    }

    public function down(): void
    {
        DB::table('billing_line_items')->truncate();
    }

    private function findFeeRateId(float $amount): ?int
    {
        $match = DB::table('fee_rates')
            ->where('amount', $amount)
            ->first();

        return $match?->id;
    }
};
