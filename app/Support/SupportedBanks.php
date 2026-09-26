<?php

namespace App\Support;

/**
 * Canonical registry of the banks the Payment Settings page accepts, with the
 * account-number shape each bank uses.
 *
 * Formats used in this table:
 *   - BDO         10/11/12 digits  (BDO officially states 12 digits; 10-11-digit legacy accounts are documented)
 *   - BPI         10 digits        (BPI correspondent-bank guidance: "Beneficiary BPI 10-digit Account Number")
 *   - Metrobank   13 digits        (Metrobank FAQ: "You can find your 13-digit account number on the debit card")
 *   - LandBank    10 digits        (documented LANDBANK savings/checking account format)
 *   - Maya        12 digits        (official Maya Bank FAQ: "Your Maya Savings account number is 12 digits long")
 *   - UnionBank   12-13 digits     (retail savings accounts are 13-digit; 12-digit legacy also documented)
 *   - RCBC / PNB / Chinabank / PSBank / DBP: 10 digits (widely documented standard lengths)
 *   - Security Bank / EastWest / GoTyme / CIMB / SeaBank / Tonik: 12 digits
 *
 * The list is intentionally a single editable constant so a bank can be added
 * or its shape corrected in one place. Any account number that does not match
 * its bank's shape is rejected at save time.
 */
final class SupportedBanks
{
    /**
     * slug => definition
     *   label          human-readable display name
     *   short          preferred short name (used in hints and receipts)
     *   digits         accepted account-number lengths (after stripping separators)
     *   aliases        additional names that resolve to this bank
     */
    public const BANKS = [
        'bdo' => [
            'label' => 'BDO (Banco de Oro)',
            'short' => 'BDO',
            'digits' => [10, 11, 12],
            'aliases' => ['BDO Unibank', 'BDO Unibank, Inc.', 'Banco de Oro'],
        ],
        'bpi' => [
            'label' => 'BPI (Bank of the Philippine Islands)',
            'short' => 'BPI',
            'digits' => [10],
            'aliases' => ['Bank of the Philippine Islands', 'BPI Family Savings Bank'],
        ],
        'metrobank' => [
            'label' => 'Metrobank',
            'short' => 'Metrobank',
            'digits' => [13],
            'aliases' => ['Metropolitan Bank', 'Metropolitan Bank and Trust Company', 'MBTC', 'Metro Bank'],
        ],
        'unionbank' => [
            'label' => 'UnionBank (Union Bank of the Philippines)',
            'short' => 'UnionBank',
            'digits' => [12, 13],
            'aliases' => ['Union Bank', 'Union Bank of the Philippines', 'UBP'],
        ],
        'landbank' => [
            'label' => 'LandBank (Land Bank of the Philippines)',
            'short' => 'LandBank',
            'digits' => [10],
            'aliases' => ['Land Bank', 'Land Bank of the Philippines', 'LANDBANK'],
        ],
        'rcbc' => [
            'label' => 'RCBC (Rizal Commercial Banking Corporation)',
            'short' => 'RCBC',
            'digits' => [10],
            'aliases' => ['Rizal Commercial Banking Corporation'],
        ],
        'pnb' => [
            'label' => 'PNB (Philippine National Bank)',
            'short' => 'PNB',
            'digits' => [10],
            'aliases' => ['Philippine National Bank'],
        ],
        'securitybank' => [
            'label' => 'Security Bank',
            'short' => 'Security Bank',
            'digits' => [12],
            'aliases' => ['Security Bank Corporation', 'SecurityBank'],
        ],
        'eastwest' => [
            'label' => 'EastWest Bank',
            'short' => 'EastWest',
            'digits' => [12],
            'aliases' => ['East West', 'East West Bank', 'East West Banking Corporation'],
        ],
        'chinabank' => [
            'label' => 'ChinaBank (China Bank Corporation)',
            'short' => 'ChinaBank',
            'digits' => [10],
            'aliases' => ['China Bank', 'China Banking Corporation', 'CBC'],
        ],
        'psbank' => [
            'label' => 'PSBank',
            'short' => 'PSBank',
            'digits' => [10],
            'aliases' => ['Philippine Savings Bank'],
        ],
        'dbp' => [
            'label' => 'DBP (Development Bank of the Philippines)',
            'short' => 'DBP',
            'digits' => [10],
            'aliases' => ['Development Bank of the Philippines'],
        ],
        'maya' => [
            'label' => 'Maya Bank',
            'short' => 'Maya',
            'digits' => [12],
            'aliases' => ['Maya Savings', 'Maya Bank, Inc.'],
        ],
        'gotyme' => [
            'label' => 'GoTyme Bank',
            'short' => 'GoTyme',
            'digits' => [12],
            'aliases' => ['Go Tyme', 'GoTyme Bank Corporation'],
        ],
        'cimb' => [
            'label' => 'CIMB Bank Philippines',
            'short' => 'CIMB',
            'digits' => [12],
            'aliases' => ['CIMB Bank', 'CIMB Philippines'],
        ],
        'seabank' => [
            'label' => 'SeaBank (SeaBank Philippines)',
            'short' => 'SeaBank',
            'digits' => [12],
            'aliases' => ['SeaBank Philippines', 'Seabank'],
        ],
        'tonik' => [
            'label' => 'Tonik Digital Bank',
            'short' => 'Tonik',
            'digits' => [12],
            'aliases' => ['Tonik Bank', 'Tonik Digital Bank, Inc.'],
        ],
    ];

    public static function all(): array
    {
        return self::BANKS;
    }

    /**
     * Resolve a typed bank name (case- and separator-insensitive) to a bank
     * definition, or null when the name is empty or unrecognised.
     */
    public static function bankFor(?string $bankName): ?array
    {
        if ($bankName === null || trim($bankName) === '') {
            return null;
        }

        $needle = self::normalize($bankName);

        foreach (self::BANKS as $slug => $def) {
            $candidates = array_merge([$def['short'], $def['label']], $def['aliases']);
            foreach ($candidates as $candidate) {
                if (self::normalize($candidate) === $needle) {
                    return ['slug' => $slug] + $def;
                }
            }
        }

        return null;
    }

    /**
     * Lowercase + strip every non-alphanumeric character so spacing, dashes,
     * and case never matter when matching a bank name.
     */
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    /**
     * Reduce an account number to its digits only (spaces/dashes removed).
     */
    public static function normalizeAccountNumber(string $number): string
    {
        return preg_replace('/[^0-9]/', '', $number) ?? '';
    }

    public static function isValidAccountNumber(string $accountNumber, string $bankSlug): bool
    {
        $def = self::BANKS[$bankSlug] ?? null;
        if ($def === null) {
            return false;
        }

        $digits = self::normalizeAccountNumber($accountNumber);
        if ($digits === '' || ! ctype_digit($digits)) {
            return false;
        }

        return in_array(strlen($digits), $def['digits'], true);
    }

    /**
     * Short human description of the required digits, e.g. "10 or 12 digits".
     */
    public static function digitsHint(string $bankSlug): string
    {
        $def = self::BANKS[$bankSlug] ?? null;
        if ($def === null) {
            return '';
        }

        $lengths = $def['digits'];
        $last = array_pop($lengths);

        return count($lengths) > 0 ? implode(', ', $lengths).' or '.$last.' digits' : $last.' digits';
    }
}