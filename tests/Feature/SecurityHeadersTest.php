<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_response_includes_security_headers(): void
    {
        $response = $this->get('https://localhost/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(self), geolocation=(), microphone=(), payment=(), usb=()');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        $csp = $response->headers->get('Content-Security-Policy')
            ?? $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_http_response_does_not_send_hsts(): void
    {
        $response = $this->get('http://localhost/login');

        $response->assertOk();
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_robots_txt_disallows_all(): void
    {
        $path = public_path('robots.txt');
        $this->assertFileExists($path);
        $body = file_get_contents($path);
        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertMatchesRegularExpression('/^Disallow:\s*\/\s*$/m', $body);
    }

    public function test_login_and_app_layouts_declare_noindex(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
