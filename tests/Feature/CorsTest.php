<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_vercel_api_preflight_allows_the_frontend_origin(): void
    {
        $response = $this->call('OPTIONS', '/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://gestion-materiel-ten.vercel.app',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $response->assertNoContent();
        $response->assertHeader(
            'Access-Control-Allow-Origin',
            'https://gestion-materiel-ten.vercel.app'
        );
    }
}
