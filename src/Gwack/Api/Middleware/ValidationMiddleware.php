<?php

namespace Gwack\Api\Middleware;

use Gwack\Api\Interfaces\MiddlewareInterface;
use Gwack\Api\Interfaces\ValidatorInterface;
use Gwack\Api\Interfaces\ValidationResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validation middleware for API requests
 *
 * Validates incoming request data against defined rules
 *
 * @package Gwack\Api\Middleware
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private ValidatorInterface $validator;
    private array $rules;
    private bool $passValidatedData;

    /**
     * Constructor
     *
     * @param ValidatorInterface $validator The validator instance
     * @param array $rules Validation rules
     * @param bool $passValidatedData Whether to pass validated data to request
     */
    public function __construct(
        ValidatorInterface $validator,
        array $rules = [],
        bool $passValidatedData = true
    ) {
        $this->validator = $validator;
        $this->rules = $rules;
        $this->passValidatedData = $passValidatedData;
    }

    /**
     * Handle the request with validation
     *
     * @param Request $request The HTTP request
     * @param callable $next The next middleware or handler
     * @return Response The HTTP response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Skip validation for OPTIONS requests
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        // Get validation rules for this request
        $rules = $this->getRulesForRequest($request);

        if (empty($rules)) {
            return $next($request);
        }

        // Validate the request
        $result = $this->validator->validate($request, $rules);

        if (!$result->isValid()) {
            return $this->createValidationErrorResponse($result);
        }

        // Pass validated data to the request
        if ($this->passValidatedData) {
            $request->attributes->set('validated_data', $result->getValidatedData());
        }

        return $next($request);
    }

    /**
     * Get validation rules for the current request
     *
     * @param Request $request The HTTP request
     * @return array Validation rules
     */
    private function getRulesForRequest(Request $request): array
    {
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        // Check for method-specific rules
        if (isset($this->rules[$method])) {
            if (isset($this->rules[$method][$path])) {
                return $this->rules[$method][$path];
            }
        }

        // Check for general rules
        if (isset($this->rules[$path])) {
            return $this->rules[$path];
        }

        // Return default rules
        return $this->rules['*'] ?? [];
    }

    /**
     * Create a validation error response
     *
     * @param ValidationResult $result The validation result
     * @return Response The error response
     */
    private function createValidationErrorResponse(ValidationResult $result): Response
    {
        $error = [
            'error' => true,
            'message' => 'Validation failed',
            'status' => 422,
            'errors' => $result->getErrors(),
        ];

        $content = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response(
            $content,
            422,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Set validation rules
     *
     * @param array $rules Validation rules
     * @return self
     */
    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Add validation rules for a specific route
     *
     * @param string $method HTTP method
     * @param string $path Route path
     * @param array $rules Validation rules
     * @return self
     */
    public function addRules(string $method, string $path, array $rules): self
    {
        $this->rules[$method][$path] = $rules;
        return $this;
    }
}
