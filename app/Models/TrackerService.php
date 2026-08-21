<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackerService extends Model
{
    use HasFactory;

    protected $table = 'tracker_services';

    protected $fillable = [
        'name',
        'category',
        'sort_order',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(TrackerInstance::class, 'service_id');
    }

    public static function seedData(): array
    {
        return [
            ['name' => 'BIR Registration', 'sort_order' => 1],
            ['name' => 'Secure New Service Invoice', 'sort_order' => 2],
            ['name' => 'Secure New Sales Invoice', 'sort_order' => 3],
            ['name' => 'Secure New Cash Voucher', 'sort_order' => 4],
            ['name' => 'Secure New Purchase Order', 'sort_order' => 5],
            ['name' => 'Secure New Cash Receipts Books', 'sort_order' => 6],
            ['name' => 'Secure New Cash Disbursement Books', 'sort_order' => 7],
            ['name' => 'Secure New Ledger Books', 'sort_order' => 8],
            ['name' => 'Secure New Journal Books', 'sort_order' => 9],
            ['name' => 'Secure Continuation of Service Invoice', 'sort_order' => 10],
            ['name' => 'Secure Continuation of Sales Invoice', 'sort_order' => 11],
            ['name' => 'Secure Continuation of Cash Voucher', 'sort_order' => 12],
            ['name' => 'Secure Continuation of Purchase Order', 'sort_order' => 13],
            ['name' => 'Secure Continuation of Cash Receipts Books', 'sort_order' => 14],
            ['name' => 'Secure Continuation of Cash Disbursement Books', 'sort_order' => 15],
            ['name' => 'Secure Continuation of Ledger Books', 'sort_order' => 16],
            ['name' => 'Secure Continuation of Journal Books', 'sort_order' => 17],
            ['name' => 'Secure New Certificate of Registration (due to lost)', 'sort_order' => 18],
            ['name' => 'Secure New Ask for Receipts (due to lost)', 'sort_order' => 19],
            ['name' => 'BIR Closure', 'sort_order' => 20],
            ['name' => 'For Compute of Open Cases', 'sort_order' => 21],
            ['name' => 'SSS Registration', 'sort_order' => 22],
            ['name' => 'Pag-IBIG Registration', 'sort_order' => 23],
            ['name' => 'PhilHealth Registration', 'sort_order' => 24],
            ['name' => 'Annual Income Tax Returns (1701 / 1702)', 'sort_order' => 25],
            ['name' => 'Quarterly Income Tax Return (1701Q / 1702Q)', 'sort_order' => 26],
            ['name' => 'Percentage Tax Return (2551Q)', 'sort_order' => 27],
            ['name' => 'VAT (2550Q)', 'sort_order' => 28],
            ['name' => 'Monthly Withholding (0619E)', 'sort_order' => 29],
            ['name' => 'Quarterly Withholding (1601EQ)', 'sort_order' => 30],
            ['name' => 'Monthly (1601C)', 'sort_order' => 31],
            ['name' => 'Audited FS', 'sort_order' => 32],
            ['name' => 'Unaudited FS', 'sort_order' => 33],
            ['name' => "Renewal of Mayor's Permit", 'sort_order' => 34],
            ['name' => 'PhilGEPS Platinum Registration', 'sort_order' => 35],
            ['name' => 'Processing of Tax Clearance', 'sort_order' => 36],
            ['name' => 'DTI Permit Assistance', 'sort_order' => 37],
            ['name' => 'Common Issues / Problems Encountered / Client Concerns', 'sort_order' => 38],
            ['name' => 'Submission of Summary of Weekly Bookkeeping', 'sort_order' => 39],
            ['name' => 'Submission of Accomplishment Weekly (Pickup, Recording, Collection)', 'sort_order' => 40],
            ['name' => 'Photocopy of Faded Receipts for Weekly Bookkeeping', 'sort_order' => 41],
            ['name' => 'Updated Receiving Copy of Bookkeeping and Back-to-Office Reports', 'sort_order' => 42],
            ['name' => 'Updated Log Book (Receiving and Outgoing) — Blue Book', 'sort_order' => 43],
            ['name' => 'Secure New Subsidiary Sales Journal', 'sort_order' => 44],
            ['name' => 'Secure New Subsidiary Purchase Journal', 'sort_order' => 45],
            ['name' => 'Secure Continuation Subsidiary Sales Journal', 'sort_order' => 46],
            ['name' => 'Secure Continuation Subsidiary Purchase Journal', 'sort_order' => 47],
            ['name' => 'Processing of Authority to Print (ATP)', 'sort_order' => 48],
            ['name' => 'Processing of TIN ID', 'sort_order' => 49],
        ];
    }
}
