<?php

namespace Gwack\Rpc\Middleware;

use Gwack\Rpc\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

/**
 * Logging middleware for RPC server
 * 
 * Logs RPC requests and responses for debugging and monitoring
 * 
 * @package Gwack\Rpc\Middleware
 */
class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;

    /**
     * @var bool Whether to log request bodies
     */
    private bool $logRequestBodies;

    /**
     * @var bool Whether to log response bodies
     */
    private bool $logResponseBodies;

    /**
     * @var int Maximum body length to log
     */
    private int $maxBodyLength;

    /**
     * Constructor
     * 
     * @param LoggerInterface $logger
     * @param bool $logRequestBodies
     * @param bool $logResponseBodies
     * @param int $maxBodyLength
     */
    public function __construct(
        LoggerInterface $logger,
        bool $logRequestBodies = true,
        bool $logResponseBodies = false,
        int $maxBodyLength = 1024
    ) {
        $this->logger = $logger;
        $this->logRequestBodies = $logRequestBodies;
        $this->logResponseBodies = $logResponseBodies;
        $this->maxBodyLength = $maxBodyLength;
    }

    /**
     * Process the request before RPC handling
     * 
     * @param Request $request The incoming request
     * @param callable $next The next middleware or handler
     * @return Response The response
     */
    public function handle(Request $request, callable $next): Response
    {
        $startTime = hrtime(true);

        // Log incoming request
        $this->logRequest($request);

        try {
            $response = $next($request);
            $duration = (hrtime(true) - $startTime) / 1e6; // Convert to milliseconds

            // Log successful response
            $this->logResponse($request, $response, $duration);

            return $response;
        } catch (\Throwable $e) {
            $duration = (hrtime(true) - $startTime) / 1e6;

            // Log error
            $this->logError($request, $e, $duration);

            throw $e;
        }
    }

    /**
     * Log incoming request
     * 
     * @param Request $request
     * @return void
     */
    private function logRequest(Request $request): void
    {
        $context = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'content_type' => $request->headers->get('Content-Type'),
        ];

        if ($this->logRequestBodies && $request->getContent()) {
            $body = $request->getContent();
            if (strlen($body) > $this->maxBodyLength) {
                $body = substr($body, 0, $this->maxBodyLength) . '...';
            }
            $context['body'] = $body;
        }

        $this->logger->info('RPC Request', $context);
    }

    /**
     * Log response
     * 
     * @param Request $request
     * @param Response $response
     * @param float $duration
     * @return void
     */
    private function logResponse(Request $request, Response $response, float $duration): void
    {
        $context = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'content_type' => $response->headers->get('Content-Type'),
        ];

        if ($this->logResponseBodies && $response->getContent()) {
            $body = $response->getContent();
            if (strlen($body) > $this->maxBodyLength) {
                $body = substr($body, 0, $this->maxBodyLength) . '...';
            }
            $context['body'] = $body;
        }

        $this->logger->info('RPC Response', $context);
    }

    /**
     * Log error
     * 
     * @param Request $request
     * @param \Throwable $error
     * @param float $duration
     * @return void
     */
    private function logError(Request $request, \Throwable $error, float $duration): void
    {
        $context = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'duration_ms' => round($duration, 2),
            'trace' => $error->getTraceAsString(),
        ];

        $this->logger->error('RPC Error', $context);
    }
}
