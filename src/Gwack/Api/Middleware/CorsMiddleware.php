<?php

namespace Gwack\Api\Middleware;

use Gwack\Api\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS middleware for API requests
 *
 * Handles Cross-Origin Resource Sharing headers for web applications
 *
 * @package Gwack\Api\Middleware
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $config;

    /**
     * Constructor
     *
     * @param array $config CORS configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
            'exposed_headers' => [],
            'max_age' => 86400,
            'allow_credentials' => false,
        ], $config);
    }

    /**
     * Handle the request and add CORS headers
     *
     * @param Request $request The HTTP request
     * @param callable $next The next middleware or handler
     * @return Response The HTTP response with CORS headers
     */
    public function handle(Request $request, callable $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request);
        }

        // Process the actual request
        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request
     *
     * @param Request $request The HTTP request
     * @return Response The preflight response
     */
    private function handlePreflightRequest(Request $request): Response
    {
        $response = new Response('', 200);

        $origin = $request->headers->get('Origin');
        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']));
        $response->headers->set('Access-Control-Max-Age', (string) $this->config['max_age']);

        if ($this->config['allow_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Add CORS headers to response
     *
     * @param Request $request The HTTP request
     * @param Response $response The HTTP response
     * @return Response The response with CORS headers
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');
        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        // Add allowed methods header for all CORS requests
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']));
        }

        if (!empty($this->config['exposed_headers'])) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->config['exposed_headers']));
        }

        if ($this->config['allow_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     *
     * @param string|null $origin The origin to check
     * @return bool True if allowed
     */
    private function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        if (in_array('*', $this->config['allowed_origins'], true)) {
            return true;
        }

        return in_array($origin, $this->config['allowed_origins'], true);
    }
}
