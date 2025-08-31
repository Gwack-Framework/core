<?php

namespace Gwack\Api\Middleware;

use Gwack\Api\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

/**
 * Logging middleware for API requests
 *
 * Logs API requests and responses for monitoring and debugging
 *
 * @package Gwack\Api\Middleware
 */
class LoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private bool $logRequests;
    private bool $logResponses;
    private bool $logHeaders;
    private bool $logBody;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger Logger instance
     * @param bool $logRequests Whether to log requests
     * @param bool $logResponses Whether to log responses
     * @param bool $logHeaders Whether to log headers
     * @param bool $logBody Whether to log request/response bodies
     */
    public function __construct(
        LoggerInterface $logger,
        bool $logRequests = true,
        bool $logResponses = true,
        bool $logHeaders = false,
        bool $logBody = false
    ) {
        $this->logger = $logger;
        $this->logRequests = $logRequests;
        $this->logResponses = $logResponses;
        $this->logHeaders = $logHeaders;
        $this->logBody = $logBody;
    }

    /**
     * Handle the request with logging
     *
     * @param Request $request The HTTP request
     * @param callable $next The next middleware or handler
     * @return Response The HTTP response
     */
    public function handle(Request $request, callable $next): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_', true);

        // Log the incoming request
        if ($this->logRequests) {
            $this->logRequest($request, $requestId);
        }

        // Process the request
        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Log the response
        if ($this->logResponses) {
            $this->logResponse($response, $requestId, $duration);
        }

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Log the incoming request
     *
     * @param Request $request The HTTP request
     * @param string $requestId Unique request ID
     * @return void
     */
    private function logRequest(Request $request, string $requestId): void
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'path' => $request->getPathInfo(),
            'query' => $request->getQueryString(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ];

        if ($this->logHeaders) {
            $context['headers'] = $this->sanitizeHeaders($request->headers->all());
        }

        if ($this->logBody && $request->getContent()) {
            $context['body'] = $this->sanitizeBody($request->getContent());
        }

        $this->logger->info('API Request', $context);
    }

    /**
     * Log the response
     *
     * @param Response $response The HTTP response
     * @param string $requestId Unique request ID
     * @param float $duration Request duration in milliseconds
     * @return void
     */
    private function logResponse(Response $response, string $requestId, float $duration): void
    {
        $context = [
            'request_id' => $requestId,
            'status' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'content_length' => strlen($response->getContent()),
        ];

        if ($this->logHeaders) {
            $context['headers'] = $response->headers->all();
        }

        if ($this->logBody && $response->getContent()) {
            $context['body'] = $this->sanitizeBody($response->getContent());
        }

        $level = $response->getStatusCode() >= 400 ? 'error' : 'info';
        $this->logger->log($level, 'API Response', $context);
    }

    /**
     * Sanitize headers for logging
     *
     * @param array $headers Request headers
     * @return array Sanitized headers
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-auth-token',
            'cookie',
            'set-cookie',
        ];

        $sanitized = [];
        foreach ($headers as $name => $values) {
            $lowerName = strtolower($name);
            if (in_array($lowerName, $sensitiveHeaders, true)) {
                $sanitized[$name] = ['[REDACTED]'];
            } else {
                $sanitized[$name] = $values;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize body content for logging
     *
     * @param string $body Request or response body
     * @return string Sanitized body
     */
    private function sanitizeBody(string $body): string
    {
        // Limit body size for logging
        if (strlen($body) > 1024) {
            $body = substr($body, 0, 1024) . '... [TRUNCATED]';
        }

        // Try to parse as JSON and sanitize sensitive fields
        $data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            $sensitiveFields = ['password', 'token', 'secret', 'key', 'auth'];

            array_walk_recursive($data, function (&$value, $key) use ($sensitiveFields) {
                if (in_array(strtolower($key), $sensitiveFields, true)) {
                    $value = '[REDACTED]';
                }
            });

            return json_encode($data);
        }

        return $body;
    }
}
