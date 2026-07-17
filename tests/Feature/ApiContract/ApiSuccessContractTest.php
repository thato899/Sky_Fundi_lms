<?php

declare(strict_types=1);

namespace Tests\Feature\ApiContract;

use Tests\TestCase;

final class ApiSuccessContractTest extends TestCase
{
    public function test_public_health_is_a_deliberate_core_specific_success_response(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['status'])
            ->assertJsonMissingPath('data');
    }

    public function test_api_routes_force_json_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'unauthenticated');
    }
}
