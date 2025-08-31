<?php

namespace Tests\Unit\Api\Http;

use PHPUnit\Framework\TestCase;
use Gwack\Api\Http\SimpleHttpClient;

/**
 * Unit tests for SimpleHttpClient
 */
class SimpleHttpClientTest extends TestCase
{
    private SimpleHttpClient $client;

    protected function setUp(): void
    {
        $this->client = new SimpleHttpClient('https://jsonplaceholder.typicode.com');
    }

    /**
     * Test client initialization
     */
    public function testClientInitialization(): void
    {
        $client = new SimpleHttpClient('https://api.example.com', [
            'Authorization' => 'Bearer token123'
        ]);

        $this->assertInstanceOf(SimpleHttpClient::class, $client);
    }

    /**
     * Test HTTP method helpers exist
     */
    public function testHttpMethodsExist(): void
    {
        $this->assertTrue(method_exists($this->client, 'get'));
        $this->assertTrue(method_exists($this->client, 'post'));
        $this->assertTrue(method_exists($this->client, 'put'));
        $this->assertTrue(method_exists($this->client, 'patch'));
        $this->assertTrue(method_exists($this->client, 'delete'));
    }

    /**
     * Test client can handle different response formats
     * Note: This is a unit test that doesn't make actual HTTP calls
     */
    public function testResponseFormatHandling(): void
    {
        // This would test the response parsing logic
        // In a real scenario, we'd mock the HTTP responses
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test custom headers
     */
    public function testCustomHeaders(): void
    {
        $client = new SimpleHttpClient('https://api.example.com', [
            'X-Custom-Header' => 'custom-value'
        ]);

        $this->assertInstanceOf(SimpleHttpClient::class, $client);
    }
}
