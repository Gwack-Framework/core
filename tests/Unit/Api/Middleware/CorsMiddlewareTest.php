<?php

namespace Tests\Unit\Api\Middleware;

use PHPUnit\Framework\TestCase;
use Gwack\Api\Middleware\CorsMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for CorsMiddleware
 */
class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new CorsMiddleware();
    }

    /**
     * Test CORS headers are added to response
     */
    public function testCorsHeadersAdded(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Origin', 'https://example.com');

        $next = function (Request $req) {
            return new Response('Test response');
        };

        $response = $this->middleware->handle($request, $next);

        // When '*' is allowed, it returns the actual origin
        $this->assertEquals('https://example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test OPTIONS preflight request
     */
    public function testOptionsPreflightRequest(): void
    {
        $request = Request::create('/api/test', 'OPTIONS');
        $request->headers->set('Origin', 'https://example.com');
        $request->headers->set('Access-Control-Request-Method', 'POST');
        $request->headers->set('Access-Control-Request-Headers', 'Content-Type');

        $next = function (Request $req) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, PUT, DELETE, PATCH, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
    }

    /**
     * Test custom CORS configuration
     */
    public function testCustomCorsConfiguration(): void
    {
        $config = [
            'allowed_origins' => ['https://trusted.com'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'max_age' => 3600
        ];

        $middleware = new CorsMiddleware($config);
        $request = Request::create('/api/test', 'OPTIONS'); // Change to OPTIONS to test preflight
        $request->headers->set('Origin', 'https://trusted.com');

        $next = function (Request $req) {
            return new Response('Test response');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals('https://trusted.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('3600', $response->headers->get('Access-Control-Max-Age'));
    }
}
