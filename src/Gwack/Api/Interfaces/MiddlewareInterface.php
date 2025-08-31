<?php

namespace Gwack\Api\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for API middleware
 *
 * Middleware can process requests before they reach controllers
 * and modify responses before they are sent to clients
 *
 * @package Gwack\Api\Interfaces
 */
interface MiddlewareInterface
{
    /**
     * Process an API request
     *
     * @param Request $request The HTTP request
     * @param callable $next The next handler in the middleware stack
     * @return Response The HTTP response
     */
    public function handle(Request $request, callable $next): Response;
}
