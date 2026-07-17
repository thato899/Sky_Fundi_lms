<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_compatible_security_headers_are_applied_to_web_and_api_responses(): void
    {
        foreach (['/up', '/api/v1/health'] as $path) {
            $this->get($path)
                ->assertSuccessful()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_hsts_is_sent_only_for_https_requests(): void
    {
        $this->get('https://localhost/api/v1/health')
            ->assertSuccessful()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
