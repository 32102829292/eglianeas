<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $types = [
        'BIR Registration',
        'Secure New Service Invoice',
        'Secure New Sales Invoice',
        'Secure New Cash Voucher',
        'Secure New Purchase Order',
        'Secure New Cash Receipts Books',
        'Secure New Cash Disbursement Books',
        'Secure New Ledger Books',
        'Secure New Journal Books',
        'Secure Continuation of Service Invoice',
        'Secure Continuation of Sales Invoice',
        'Secure Continuation of Cash Voucher',
        'Secure Continuation of Purchase Order',
        'Secure Continuation of Cash Receipts Books',
        'Secure Continuation of Cash Disbursement Books',
        'Secure Continuation of Ledger Books',
        'Secure Continuation of Journal Books',
        'Secure New Certificate of Registration (due to lost)',
        'Secure New Ask for Receipts (due to lost)',
        'BIR Closure',
        'For Compute of Open Cases',
        'SSS Registration',
        'Pag-IBIG Registration',
        'PhilHealth Registration',
        'Annual Income Tax Returns (1701 or 1702Q)',
        'Quarterly Income Tax Return (1701Q or 1702Q)',
        'Percentage Tax (2551Q)',
        'VAT (2550Q)',
        'Monthly Withholding (0619E)',
        'Monthly Withholding (1601EQ)',
        'Monthly (1601C)',
        'Audited FS',
        'Unaudited FS',
        'Renewal of Mayor\'s Permit',
        'PhilGEPS Platinum Registration',
        'Processing of Tax Clearance',
        'DTI Permit Assistance',
    ];

    public function up(): void
    {
        DB::statement('DELETE FROM service_types');

        DB::table('service_types')->insert(
            array_map(fn(string $label, int $i) => [
                'label'      => $label,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ], $this->types, range(0, count($this->types) - 1))
        );
    }

    public function down(): void
    {
        // Irreversible — original data unknown.
    }
};
