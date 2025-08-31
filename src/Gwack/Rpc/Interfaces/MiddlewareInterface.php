<?php

namespace Gwack\Rpc\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for RPC middleware
 * 
 * Allows processing of requests and responses before and after
 * the main RPC handling logic.
 * 
 * @package Gwack\Rpc\Interfaces
 */
interface MiddlewareInterface
{
    /**
     * Process the request before RPC handling
     * 
     * @param Request $request The incoming request
     * @param callable $next The next middleware or handler
     * @return Response The response
     */
    public function handle(Request $request, callable $next): Response;
}
