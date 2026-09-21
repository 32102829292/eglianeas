<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Assemblies the payment-details payload shown in the billing statement PDFs
 * (single-statement attachment and multi-statement print batch) from the
 * application's existing payment settings (gcash_number, gcash_qr_code and
 * bank_accounts). Images are resolved to base64 data URIs so DomPDF can embed
 * them without requiring remote-image fetching. When a QR path is missing or
 * unreadable it is resolved to null and the templates omit the <img> entirely,
 * so a broken-image placeholder is never produced.
 */
class BillingPaymentDetails
{
    /**
     * @return array{gcash_number: string, gcash_qr: string|null, banks: array<int, array{bank_name: string, account_name: string, account_number: string, label: string, qr: string|null}>, has: bool}
     */
    public static function forPdf(): array
    {
        $gcashNumber = trim((string) Setting::get('gcash_number', ''));
        $gcashQr = static::qrDataUri((string) Setting::get('gcash_qr_code', ''));

        $banks = [];
        foreach ((array) Setting::get('bank_accounts', []) as $bank) {
            if (! is_array($bank)) {
                continue;
            }

            $bankName = trim((string) ($bank['bank_name'] ?? ''));
            $accountName = trim((string) ($bank['account_name'] ?? ''));
            $accountNumber = trim((string) ($bank['account_number'] ?? ''));

            if ($bankName === '' && $accountName === '' && $accountNumber === '') {
                continue;
            }

            // Single-line label for compact layouts (e.g. the batch footer).
            // A plain middle dot keeps the rendered output free of HTML entities.
            $label = implode(' · ', array_filter(
                [$bankName, $accountNumber, $accountName],
                fn (string $value) => $value !== ''
            ));

            $banks[] = [
                'bank_name' => $bankName,
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'label' => $label,
                'qr' => static::qrDataUri((string) ($bank['bank_qr_code'] ?? '')),
            ];
        }

        return [
            'gcash_number' => $gcashNumber,
            'gcash_qr' => $gcashQr,
            'banks' => $banks,
            'has' => $gcashNumber !== '' || $gcashQr !== null || count($banks) > 0,
        ];
    }

    /**
     * Resolve a stored QR-code path (supabase disk) to a base64 data URI, or
     * null when the setting is empty or the object cannot be read.
     */
    private static function qrDataUri(string $path): ?string
    {
        $path = trim($path);

        if ($path === '' || ! Storage::disk('supabase')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('supabase')->get($path);
        if ($bytes === null || strlen($bytes) === 0) {
            return null;
        }

        $mime = Storage::disk('supabase')->mimeType($path) ?: static::mimeFromExtension($path);

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private static function mimeFromExtension(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            default => 'image/png',
        };
    }
}