<?php

namespace Tests\Unit;

use App\Support\UrlLink;
use PHPUnit\Framework\TestCase;

class UrlLinkTest extends TestCase
{
    public function test_https_url_is_detected(): void
    {
        $this->assertSame('https://example.com', UrlLink::href('https://example.com'));
        $this->assertSame(
            'https://www.facebook.com/angelimae.gabaut/@else',
            UrlLink::href('https://www.facebook.com/angelimae.gabaut/@else')
        );
    }

    public function test_http_url_is_detected(): void
    {
        $this->assertSame('http://example.com', UrlLink::href('http://example.com'));
    }

    public function test_www_host_is_normalized_to_https(): void
    {
        $this->assertSame('https://www.example.com', UrlLink::href('www.example.com'));
        $this->assertSame('https://www.example.com/path?q=1', UrlLink::href('www.example.com/path?q=1'));
    }

    public function test_plain_text_is_not_a_url(): void
    {
        $this->assertNull(UrlLink::href('John Dela Cruz'));
        $this->assertNull(UrlLink::href('0918 765 4321'));
        $this->assertNull(UrlLink::href('123 Rizal Avenue, Quezon City'));
        $this->assertNull(UrlLink::href('Simply a normal note.'));
        $this->assertNull(UrlLink::href('angelimae.gabaut'));
    }

    public function test_unsafe_schemes_are_rejected(): void
    {
        $this->assertNull(UrlLink::href('javascript:alert(1)'));
        $this->assertNull(UrlLink::href('JaVaScRiPt:alert(1)'));
        $this->assertNull(UrlLink::href('data:text/html;base64,PHNjcmlwdD4='));
        $this->assertNull(UrlLink::href('vbscript:msgbox(1)'));
        $this->assertNull(UrlLink::href('file:///etc/passwd'));
        $this->assertNull(UrlLink::href('https://example.com" onclick="alert(1)'));
    }

    public function test_mixed_text_with_url_is_left_alone(): void
    {
        $this->assertNull(UrlLink::href('Visit https://example.com now'));
    }

    public function test_bare_domain_without_scheme_or_www_is_not_linked(): void
    {
        $this->assertNull(UrlLink::href('example.com'));
    }

    public function test_empty_and_non_string_values_are_not_linked(): void
    {
        $this->assertNull(UrlLink::href(''));
        $this->assertNull(UrlLink::href('   '));
        $this->assertNull(UrlLink::href(null));
        $this->assertNull(UrlLink::href(12345));
    }

    public function test_very_long_url_is_detected(): void
    {
        $long = 'https://example.com/'.str_repeat('segment/', 40);
        $this->assertSame($long, UrlLink::href($long));
    }

    public function test_localhost_and_ip_literals_are_detected(): void
    {
        $this->assertSame('https://localhost:8000/path', UrlLink::href('https://localhost:8000/path'));
        $this->assertSame('http://192.168.1.5/admin', UrlLink::href('http://192.168.1.5/admin'));
    }
}