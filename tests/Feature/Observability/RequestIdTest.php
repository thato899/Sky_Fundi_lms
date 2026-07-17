<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Support\Str;
use Tests\TestCase;

final class RequestIdTest extends TestCase
{
    public function test_a_request_id_is_generated_and_returned_for_every_http_request(): void
    {
        $response = $this->get('/up')->assertOk();
        $requestId = $response->headers->get('X-Request-ID');

        $this->assertIsString($requestId);
        $this->assertTrue(Str::isUuid($requestId));
    }

    public function test_a_valid_inbound_request_id_is_preserved(): void
    {
        $requestId = (string) Str::uuid();

        $this->withHeader('X-Request-ID', $requestId)
            ->get('/up')
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId);
    }

    public function test_malformed_or_unbounded_inbound_request_ids_are_replaced(): void
    {
        foreach (["not-a-uuid\r\nforged", str_repeat('a', 500)] as $invalid) {
            $response = $this->withHeader('X-Request-ID', $invalid)->get('/up')->assertOk();
            $requestId = $response->headers->get('X-Request-ID');

            $this->assertIsString($requestId);
            $this->assertTrue(Str::isUuid($requestId));
            $this->assertNotSame($invalid, $requestId);
        }
    }
}
