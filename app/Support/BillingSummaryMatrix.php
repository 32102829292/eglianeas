<?php

namespace App\Support;

use App\Models\Billing;
use App\Models\BillingLineItem;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the Billing Summary matrix.
 *
 * The XLSX workbook, the web table and the print/PDF all render from this one
 * object so a column can never exist in one output but not another, and so
 * every subtotal is computed exactly once. Nothing here writes to the database
 * or changes how billing amounts are derived — it only arranges the existing
 * line items into the grouped XLSX layout.
 *
 * Column order mirrors the workbook exactly:
 *
 *   CLIENT | FOR REMITTANCE (…BIR, Subtotal) | FOR FEE (Cash In, …fees, Subtotal)
 *          | TOTAL | GRAND TOTAL
 */
final class BillingSummaryMatrix
{
    /**
     * Fee-group columns that follow the dynamic per-form "FEE — xxxx" columns.
     * Each maps a line-item category to its workbook header.
     *
     * @var array<string, string>
     */
    private const FEE_COLUMNS = [
        'bookkeeping_fee' => 'Bookkeeping Fee',
        'post_closing_tb' => 'Post-Closing TB',
        'inventory_list' => 'Inventory List',
        'other_attachment' => 'Other Attachment',
        'data_entry' => 'Data Entry',
    ];

    /**
     * Line items stored under the `custom` category have no dedicated column in
     * the workbook layout, but they ARE real money and are already counted in
     * the fee subtotal. They get a trailing column inside FOR FEE so the visible
     * columns always reconcile with the subtotal instead of silently dropping
     * the amount.
     */
    public const CUSTOM_FEE_LABEL = 'Custom Fee';

    /**
     * @param  Collection<int, Billing>  $billings
     */
    public function __construct(private readonly Collection $billings)
    {
    }

    /**
     * Build the matrix for a set of billings.
     *
     * @param  Collection<int, Billing>  $billings
     */
    public static function make(Collection $billings): self
    {
        return new self($billings);
    }

    /**
     * Every form type present in the period, sorted, so the BIR and Fee column
     * pairs always line up. Derived from the data (not hardcoded) so a new BIR
     * form type starts appearing automatically.
     *
     * @return array<int, string>
     */
    public function formTypes(): array
    {
        $types = $this->billings
            ->flatMap(fn (Billing $billing) => $billing->lineItems->pluck('form_type')->filter())
            ->unique()
            ->values()
            ->all();

        sort($types);

        return $types;
    }

    /**
     * True when any line item in the period uses the `custom` category, which
     * requires the trailing Custom Fee column.
     */
    public function hasCustomFees(): bool
    {
        return $this->billings->contains(
            fn (Billing $billing) => $billing->lineItems->contains('category', 'custom')
        );
    }

    /**
     * Flattened column model shared by the web table, the workbook and the PDF.
     *
     * Each entry is [key, group, level-2 header, is-subtotal]. `group` is null
     * for the columns that span both header rows (CLIENT, TOTAL, GRAND TOTAL).
     *
     * @return array<int, array{key: string, group: ?string, label: string, subtotal: bool}>
     */
    public function columns(): array
    {
        $columns = [
            ['key' => 'client', 'group' => null, 'label' => 'Client', 'subtotal' => false],
        ];

        // FOR REMITTANCE: dynamic BIR-form columns come from the period's
        // line items, so new forms need no hardcoded export update.
        foreach ($this->formTypes() as $formType) {
            $columns[] = [
                'key' => 'bir:'.$formType,
                'group' => 'remittance',
                'label' => $formType.' (BIR)',
                'subtotal' => false,
            ];
        }

        $columns[] = [
            'key' => 'remittance_subtotal',
            'group' => 'remittance',
            'label' => 'Subtotal for Remittance (BIR)',
            'subtotal' => true,
        ];

        $columns[] = ['key' => 'cash_in', 'group' => 'fee', 'label' => 'Cash In', 'subtotal' => false];

        // FEE group - dynamic per-form fees
        foreach ($this->formTypes() as $formType) {
            $columns[] = [
                'key' => 'fee:'.$formType,
                'group' => 'fee',
                'label' => 'FEE — '.$formType,
                'subtotal' => false,
            ];
        }

        // Other billing categories belong to the same FOR FEE group.
        foreach (self::FEE_COLUMNS as $category => $label) {
            $columns[] = ['key' => $category, 'group' => 'fee', 'label' => $label, 'subtotal' => false];
        }

        if ($this->hasCustomFees()) {
            $columns[] = ['key' => 'custom', 'group' => 'fee', 'label' => self::CUSTOM_FEE_LABEL, 'subtotal' => false];
        }

        $columns[] = [
            'key' => 'fee_subtotal',
            'group' => 'fee',
            'label' => 'Subtotal for Fee (Fee / Cash In)',
            'subtotal' => true,
        ];

        $columns[] = ['key' => 'total', 'group' => null, 'label' => 'Total', 'subtotal' => false];
        $columns[] = ['key' => 'grand_total', 'group' => null, 'label' => 'Grand Total', 'subtotal' => true];

        return $columns;
    }

    /**
     * Column counts per top-level group, used to size colspans in the grouped
     * header and to detect the two header rows are balanced.
     *
     * @return array<string, int>
     */
    public function groupSpans(): array
    {
        $spans = [];

        foreach ($this->columns() as $column) {
            if ($column['group'] !== null) {
                $spans[$column['group']] = ($spans[$column['group']] ?? 0) + 1;
            }
        }

        return $spans;
    }

    /**
     * One row per billing statement, keyed by column key.
     *
     * `total` intentionally reports the stored `billings.total` (the invoiced
     * amount of record) while `grand_total` reports remittance + fee derived
     * from line items. Those can differ on legacy rows whose client was
     * deleted or whose line items were removed; the difference is exposed by
     * hasTotalVariance() rather than being papered over.
     *
     * @return array<int, array<string, float|string|int|null>>
     */
    public function rows(): array
    {
        $formTypes = $this->formTypes();

        return $this->billings->map(function (Billing $billing) use ($formTypes): array {
            $lineItems = $billing->lineItems;
            $row = [
                'billing_id' => $billing->id,
                'client' => $billing->client?->business_name ?: ($billing->client?->name ?? ''),
                'orphan' => $billing->client === null,
            ];

            $remittance = 0.0;

            foreach ($formTypes as $formType) {
                $amount = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
                    ->where('form_type', $formType)
                    ->sum('amount');
                $row['bir:'.$formType] = $amount;
                $remittance += $amount;
            }

            // Cash In is a BIR-remittance line item that simply has no form
            // type, so it is part of the BIR Forms Filed subtotal.
            $cashIn = (float) $lineItems
                ->where('category', BillingLineItem::CATEGORY_BIR_REMITTANCE)
                ->whereNull('form_type')
                ->sum('amount');
            $row['cash_in'] = $cashIn;
            $row['remittance_subtotal'] = round($remittance, 2);

            $fee = $cashIn;

            foreach ($formTypes as $formType) {
                $amount = (float) $lineItems
                    ->where('category', BillingLineItem::CATEGORY_PROFESSIONAL_FEE)
                    ->where('form_type', $formType)
                    ->sum('amount');
                $row['fee:'.$formType] = $amount;
                $fee += $amount;
            }

            foreach (self::FEE_COLUMNS as $category => $label) {
                $amount = (float) $lineItems->where('category', $category)->sum('amount');
                $row[$category] = $amount;
                $fee += $amount;
            }

            if ($this->hasCustomFees()) {
                $amount = (float) $lineItems->where('category', 'custom')->sum('amount');
                $row['custom'] = $amount;
                $fee += $amount;
            }

            $row['fee_subtotal'] = round($fee, 2);

            $row['total'] = round((float) $billing->total, 2);
            $row['grand_total'] = round($remittance + $fee, 2);

            return $row;
        })->all();
    }

    /**
     * Column totals for the footer row.
     *
     * @return array<string, float>
     */
    public function columnTotals(): array
    {
        $rows = $this->rows();
        $totals = [];

        foreach ($this->columns() as $column) {
            if ($column['key'] === 'client') {
                continue;
            }

            $totals[$column['key']] = round(array_sum(array_map(
                fn (array $row): float => (float) ($row[$column['key']] ?? 0),
                $rows
            )), 2);
        }

        return $totals;
    }

    /**
     * BILLING RECEIPT SUMMARY rows: one per statement with its remittance, fee
     * and grand total, in the same order as the main table.
     *
     * @return array<int, array<string, float|string|bool>>
     */
    public function receiptRows(): array
    {
        return array_map(function (array $row): array {
            return [
                'billing_id' => $row['billing_id'],
                'client' => $row['client'],
                'orphan' => $row['orphan'],
                'remittance' => $row['remittance_subtotal'],
                'fee' => $row['fee_subtotal'],
                'grand_total' => $row['grand_total'],
            ];
        }, $this->rows());
    }

    /**
     * Bottom-line figures for the receipt summary.
     *
     * @return array{remittance: float, fee: float, grand_total: float}
     */
    public function grandTotals(): array
    {
        $remittance = 0.0;
        $fee = 0.0;
        $grand = 0.0;

        foreach ($this->receiptRows() as $row) {
            $remittance += $row['remittance'];
            $fee += $row['fee'];
            $grand += $row['grand_total'];
        }

        return [
            'remittance' => round($remittance, 2),
            'fee' => round($fee, 2),
            'grand_total' => round($grand, 2),
        ];
    }

    /**
     * Rows whose stored `total` disagrees with the line-item grand total.
     *
     * @return array<int, array<string, mixed>>
     */
    public function variances(): array
    {
        return array_values(array_filter($this->rows(), function (array $row): bool {
            return abs($row['total'] - $row['grand_total']) > 0.005;
        }));
    }

    public function isEmpty(): bool
    {
        return $this->billings->isEmpty();
    }
}
