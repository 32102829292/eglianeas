<?php

namespace Database\Seeders;

use App\Models\TeamMember;
use Illuminate\Database\Seeder;

class TeamMemberSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'position' => 'Manager',
                'name' => 'Laira',
                'department' => 'General Management',
                'reports_to' => 'Owner',
                'supervises' => 'Accounting Supervisor, Consultant/Innovator, Liaison & Quality Control Officer',
                'rank' => 'Managerial',
                'duties' => 'Enforces the 3–5 day internal deadline rule before official BIR/government filing dates; oversees daily workflow balancing across staff; drives the 3-Tray System (In/Ongoing/Out) and Google Drive backup routines; leads weekly status reviews on high-risk client backlogs and collections; evaluates delinquent accounts for work suspension with Credit & Collections.',
                'sort_order' => 1,
            ],
            [
                'position' => 'Accounting Supervisor',
                'name' => 'Lorez',
                'department' => null,
                'reports_to' => 'Manager',
                'supervises' => 'Financial Analyst, Bookkeeping & Promotion Analyst, Bookkeeping Account & Collection Officer, Account Specialist',
                'rank' => 'Supervisory',
                'duties' => 'Enforces mandatory 2-step verification on taxpayer entries, TIN matching, tax form selection, and payment details before filing; reviews and approves journal entries, cash receipts/disbursements, VAT records, and Alphalists; directs month-end/year-end closing (ITR 1701/1702, 2550Q, 1601C, 1601EQ); conducts staff training on account classification and data encoding standards.',
                'sort_order' => 2,
            ],
            [
                'position' => 'Financial Analyst',
                'name' => 'Aji',
                'department' => null,
                'reports_to' => 'Accounting Supervisor / Manager',
                'supervises' => null,
                'rank' => 'Supervisory / Specialist',
                'duties' => 'Builds monthly and annual financial projections from client data; prepares financial statement variance analysis; implements unified Charts of Accounts and standardized Trial Balance formats; conducts cost-efficiency and cash flow risk assessments; generates periodic financial insight reports and dashboards.',
                'sort_order' => 3,
            ],
            [
                'position' => 'Liaison & Quality Control Officer',
                'name' => 'Diane',
                'department' => null,
                'reports_to' => 'Manager',
                'supervises' => null,
                'rank' => 'Supervisory / Specialist',
                'duties' => 'Represents the company at government agencies (BIR, Mayor\'s Office, SEC) for registrations, permits, ATP, and clearances; enforces document retention (receiving copies and proof of payment before releasing originals to clients); manages valid ID and transmittal documentation; audits physical document storage and labeling; monitors penalty resolutions and BIR closure applications.',
                'sort_order' => 4,
            ],
            [
                'position' => 'Consultant / Innovator',
                'name' => 'Ian',
                'department' => null,
                'reports_to' => 'Manager / Owner',
                'supervises' => 'Project Team Support (if applicable)',
                'rank' => 'Technical Specialist / Advisory',
                'duties' => 'Builds automated Excel tools (e.g. auto date-stamping) to prevent duplicate filings; sets up automated cloud backups (Google Drive/OneDrive) every other day; conducts workflow bottleneck evaluations and updates SOP documentation; advises on software adoption and system modernization; designs automated compliance-monitoring dashboards for filing deadlines.',
                'sort_order' => 5,
            ],
            [
                'position' => 'Consultant / Innovator',
                'name' => 'Joshua',
                'department' => null,
                'reports_to' => 'Manager / Owner',
                'supervises' => 'Project Team Support (if applicable)',
                'rank' => 'Technical Specialist / Advisory',
                'duties' => 'Builds automated Excel tools (e.g. auto date-stamping) to prevent duplicate filings; sets up automated cloud backups (Google Drive/OneDrive) every other day; conducts workflow bottleneck evaluations and updates SOP documentation; advises on software adoption and system modernization; designs automated compliance-monitoring dashboards for filing deadlines.',
                'sort_order' => 6,
            ],
            [
                'position' => 'Bookkeeping and Promotion Analyst',
                'name' => 'Ara',
                'department' => null,
                'reports_to' => 'Accounting Supervisor',
                'supervises' => null,
                'rank' => 'Rank and File / Analyst',
                'duties' => 'Records general ledger entries, purchase/sales journals, and cash vouchers; follows the "no liquid eraser" correction rule for ledger auditability; tracks promotional/campaign expenses against budget and calculates ROI; organizes ledger books and documents using the 3-Tray System; double-checks eBIR form selections before supervisor review.',
                'sort_order' => 7,
            ],
            [
                'position' => 'Account Specialist',
                'name' => 'Korina',
                'department' => null,
                'reports_to' => 'Accounting Supervisor',
                'supervises' => null,
                'rank' => 'Rank and File / Specialist',
                'duties' => 'Primary point of contact for assigned client accounts; maintains the Client Concern Tracker (issue date, responsible person, target resolution date); distributes document checklists ahead of tax deadlines (Forms 2307, 2316, COR); manages digital contact directories and client community channels; secures/verifies client portal credentials (eFPS, eBIRForms, ORUS).',
                'sort_order' => 8,
            ],
            [
                'position' => 'Bookkeeping Account and Collection Officer',
                'name' => 'Archie',
                'department' => null,
                'reports_to' => 'Accounting Supervisor',
                'supervises' => null,
                'rank' => 'Rank and File',
                'duties' => 'Maintains Accounts Receivable aging ledgers and payment status tracking; classifies client accounts into triage tiers (Current, Pending, Delinquent, Critical); prepares billing statements and collection letters; implements work-stoppage protocols for delinquent accounts; reconciles payment receipts with ledger entries.',
                'sort_order' => 9,
            ],
        ];

        foreach ($members as $member) {
            TeamMember::updateOrCreate(
                ['position' => $member['position'], 'name' => $member['name']],
                $member
            );
        }

        TeamMember::where('name', 'Ian and Joshua')->delete();
    }
}
