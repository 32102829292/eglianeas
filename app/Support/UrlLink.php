<?php

namespace App\Support;

/**
 * Safe URL detection for display-only fields.
 *
 * Only ever returns http:// or https:// hrefs. Everything else (plain text,
 * phone numbers, usernames, masked values, executable schemes) yields null,
 * so callers fall back to rendering the raw escaped value as normal text.
 */
class UrlLink
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Return a normalized, safe href when $value is (or can safely be treated as)
     * a single http/https URL; return null otherwise.
     */
    public static function href(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        // A field-level URL must be a single token. Rejecting whitespace keeps
        // names, addresses, notes, phrases, and phone numbers as plain text.
        if (preg_match('/\s/u', $trimmed)) {
            return null;
        }

        // Never accept characters that could break out of an HTML attribute.
        if (preg_match('/["\'`<>\\\\]/', $trimmed)) {
            return null;
        }

        $candidate = $trimmed;

        // Convert a bare "www.example.com/path" into a safe https URL, but only
        // when it clearly starts with an explicit www. host label.
        if (! preg_match('~^https?://~i', $candidate)
            && preg_match('~^www\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+~i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        // Reject known executable / custom URL schemes before parsing.
        if (preg_match('~^(?:javascript|data|vbscript|file|blob|about|chrome|view-source):~i', $candidate)) {
            return null;
        }

        $parsed = parse_url($candidate);
        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host']) || $parsed['host'] === '') {
            return null;
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        $host = $parsed['host'];

        // Reject hosts with whitespace, quotes, angle brackets, or control/odd chars.
        if (preg_match('/[\s<>"\'\\\\\x00-\x1F\x7F]/', $host) || preg_match('~[^\x21-\x7E]~', $host)) {
            return null;
        }

        // Require a plausible host (dotted domain, localhost, IP, or bracketed
        // IPv6) so bare words like "facebook" are never turned into links.
        $hostLower = strtolower($host);
        $ipv4 = preg_match('~^\d{1,3}(?:\.\d{1,3}){3}$~', $host) === 1;
        $ipv6 = str_starts_with($host, '[') && str_ends_with($host, ']');
        $dotted = str_contains($host, '.');

        if (! $dotted && $hostLower !== 'localhost' && ! $ipv4 && ! $ipv6) {
            return null;
        }

        return $candidate;
    }
}