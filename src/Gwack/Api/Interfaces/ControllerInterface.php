<?php

namespace Gwack\Api\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for API controllers
 *
 * Defines the contract for REST API controllers in the framework
 *
 * @package Gwack\Api\Interfaces
 */
interface ControllerInterface
{
    /**
     * Handle an HTTP request and return a response

     * @param Request $request The HTTP request
     * @param array $params Route parameters
     * @return Response The HTTP response
     */
    public function handle(Request $request, array $params = []): Response;
}
