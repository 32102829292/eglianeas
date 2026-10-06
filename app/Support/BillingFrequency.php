<?php

namespace App\Support;

use App\Models\BillingLineItem;

/**
 * Billing frequency for a chargeable service.
 *
 * The billing page used to render one amount field per service with no
 * indication of how often a service actually recurs, so every row read as
 * though it belonged to the current statement. Frequency is what lets the page
 * distinguish "this is charged every quarter" from "this is charged once a year"
 * and therefore must never be pre-ticked.
 *
 * Frequencies are a property of the service, not of the statement, so they are
 * derived from the BIR form code / billing category rather than stored per
 * client. The value is still snapshotted onto each billing_line_items row when
 * a statement is saved, so a historical statement keeps showing the frequency
 * it was billed under even if this map is ever revised.
 */
final class BillingFrequency
{
    public const MONTHLY = 'monthly';

    public const QUARTERLY = 'quarterly';

    public const ANNUAL = 'annual';

    public const ONE_TIME = 'one_time';

    public const AS_NEEDED = 'as_needed';

    public const UNKNOWN = 'unknown';

    /** Human labels. Kept short because they render inside small badges. */
    public const LABELS = [
        self::MONTHLY => 'Monthly',
        self::QUARTERLY => 'Quarterly',
        self::ANNUAL => 'Annual',
        self::ONE_TIME => 'One-Time',
        self::AS_NEEDED => 'As Needed',
        self::UNKNOWN => '—',
    ];

    /**
     * Frequencies that recur every billing cycle, so they may be pre-ticked.
     *
     * Everything outside this list (annual, one-time, as-needed) is deliberately
     * excluded: an annual return must never be silently carried into a quarter
     * it was not filed in, so the admin has to opt in to it explicitly.
     *
     * @var array<int, string>
     */
    public const RECURRING = [self::MONTHLY, self::QUARTERLY];

    /**
     * Frequency per BIR form code.
     *
     * Mirrors the seeded `bir_form_types` master list. 1701/1702 are the annual
     * income tax returns; the 1601C/0619E/0619F/2550M codes are the monthly
     * withholdings; the rest file quarterly.
     *
     * @var array<string, string>
     */
    public const FORM_FREQUENCIES = [
        'EFPS' => self::AS_NEEDED,
        '2551Q' => self::QUARTERLY,
        '1701' => self::ANNUAL,
        '1701Q' => self::QUARTERLY,
        '2550Q' => self::QUARTERLY,
        '1601C' => self::MONTHLY,
        '1601EQ' => self::QUARTERLY,
        '0619E' => self::MONTHLY,
        '2550M' => self::MONTHLY,
        '0619F' => self::MONTHLY,
        '1601FQ' => self::QUARTERLY,
        '1702Q' => self::QUARTERLY,
        '1702' => self::ANNUAL,
    ];

    /**
     * Frequency per non-BIR billing category.
     *
     * Bookkeeping is a standing monthly engagement billed on the quarterly
     * statement. The attachment categories are prepared on request, so they are
     * treated as optional rather than assumed every quarter.
     *
     * @var array<string, string>
     */
    public const CATEGORY_FREQUENCIES = [
        BillingLineItem::CATEGORY_BOOKKEEPING_FEE => self::MONTHLY,
        BillingLineItem::CATEGORY_POST_CLOSING_TB => self::AS_NEEDED,
        BillingLineItem::CATEGORY_INVENTORY_LIST => self::AS_NEEDED,
        BillingLineItem::CATEGORY_OTHER_ATTACHMENT => self::AS_NEEDED,
        BillingLineItem::CATEGORY_DATA_ENTRY => self::AS_NEEDED,
        BillingLineItem::CATEGORY_CUSTOM => self::ONE_TIME,
    ];

    /**
     * Resolve the frequency of a line item from its category and BIR form code.
     *
     * A BIR form code always wins over the category default, because "1701
     * Remittance" is annual while a bare bookkeeping row is monthly.
     */
    public static function forLineItem(string $category, ?string $formType = null): string
    {
        if ($formType) {
            return self::FORM_FREQUENCIES[$formType] ?? self::UNKNOWN;
        }

        // Cash In has no form type: it is a manual offset against a remittance.
        if ($category === BillingLineItem::CATEGORY_BIR_REMITTANCE) {
            return self::AS_NEEDED;
        }

        return self::CATEGORY_FREQUENCIES[$category] ?? self::UNKNOWN;
    }

    public static function label(?string $frequency): string
    {
        return self::LABELS[$frequency ?? self::UNKNOWN] ?? self::LABELS[self::UNKNOWN];
    }

    public static function isKnown(?string $frequency): bool
    {
        return $frequency !== null && $frequency !== self::UNKNOWN && isset(self::LABELS[$frequency]);
    }

    /** Whether a frequency repeats every billing cycle. */
    public static function isRecurring(?string $frequency): bool
    {
        return $frequency !== null && in_array($frequency, self::RECURRING, true);
    }

    /**
     * Whether a row may be pre-ticked when a statement is opened.
     *
     * Only genuinely recurring services qualify. Annual, one-time and as-needed
     * services always start unticked and must be included deliberately.
     */
    public static function isPreTicked(?string $frequency): bool
    {
        return self::isRecurring($frequency);
    }

    /**
     * Short note explaining why a row is not pre-ticked, so the admin is never
     * left wondering whether an empty row was an oversight.
     */
    public static function notScheduledNote(?string $frequency): ?string
    {
        return match ($frequency) {
            self::ANNUAL => 'Not scheduled for this quarter',
            self::ONE_TIME => 'One-time charge — include manually',
            self::AS_NEEDED => 'Only when requested',
            default => null,
        };
    }

    /** Frequencies offered by the custom-item modal. */
    public static function selectable(): array
    {
        return [
            self::ONE_TIME => self::LABELS[self::ONE_TIME],
            self::MONTHLY => self::LABELS[self::MONTHLY],
            self::QUARTERLY => self::LABELS[self::QUARTERLY],
            self::ANNUAL => self::LABELS[self::ANNUAL],
            self::AS_NEEDED => self::LABELS[self::AS_NEEDED],
        ];
    }

    /** The whole map, for the billing page's client-side frequency lookup. */
    public static function toJsMaps(): array
    {
        return [
            'forms' => self::FORM_FREQUENCIES,
            'categories' => self::CATEGORY_FREQUENCIES,
            'recurring' => self::RECURRING,
            'labels' => self::LABELS,
        ];
    }
}