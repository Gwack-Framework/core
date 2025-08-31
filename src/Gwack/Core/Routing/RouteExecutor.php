<?php

namespace Gwack\Core\Routing;

use Gwack\Container\Container;
use Gwack\Core\Context;
use Gwack\Http\Request;
use Gwack\Core\Resolvers\FunctionResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route Executor
 *
 * Executes route files with proper container-resolved context
 * Provides framework functions and services to route handlers
 */
class RouteExecutor
{
    private Container $container;
    private FunctionResolver $functionResolver;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->functionResolver = new FunctionResolver($container);
    }

    /**
     * Execute a route file with container-resolved context
     *
     * @param string $filePath Path to the route file
     * @param Context $context Framework context
     * @param Request $request Current request
     * @return Response
     */
    public function execute(string $filePath, Context $context, Request $request): Response
    {
        // Get framework functions from container
        $functions = $this->container->getFunctions();

        // Create isolated execution environment
        $executor = function () use ($filePath, $context, $request, $functions) {
            // Extract functions into local scope
            extract($functions, EXTR_SKIP);

            // Load the route file
            $handler = include $filePath;

            // If the file returns a callable, execute it
            if (is_callable($handler)) {
                return $handler($context, $request);
            }

            // If the file executed directly and returned a response
            if ($handler instanceof Response) {
                return $handler;
            }

            // Fallback: create empty response
            return $functions['response']('');
        };

        $result = $executor();
        // Ensure we always return a Response object
        if (!$result instanceof Response) {
            // Get json function from container
            $jsonFunction = $this->container->getFunction('json');
            return $jsonFunction ? $jsonFunction($result) : new Response(json_encode($result));
        }

        return $result;
    }
}
