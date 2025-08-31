<?php

namespace Gwack\Rpc\Handlers;

use Gwack\Rpc\Interfaces\RpcHandlerInterface;
use Gwack\Rpc\Exceptions\MethodNotFoundException;
use Gwack\Rpc\Exceptions\InvalidParamsException;
use Gwack\Rpc\Exceptions\RpcException;
use Psr\Container\ContainerInterface;

/**
 * Service handler for RPC calls
 *
 * Handles method calls on service objects with automatic dependency
 * injection, parameter validation, and reflection caching.
 *
 * @package Gwack\Rpc\Handlers
 */
class ServiceHandler implements RpcHandlerInterface
{
    /**
     * @var object The service instance
     */
    private object $service;

    /**
     * @var string The service class name
     */
    private string $serviceClass;

    /**
     * @var array Cached reflection methods
     */
    private array $methodCache = [];

    /**
     * @var array Cached method metadata
     */
    private array $metadataCache = [];

    /**
     * @var ContainerInterface|null Dependency injection container
     */
    private ?ContainerInterface $container;

    /**
     * @var array Allowed method patterns
     */
    private array $allowedMethods = [];

    /**
     * @var array Denied method patterns
     */
    private array $deniedMethods = [
        '__construct',
        '__destruct',
        '__clone',
        '__sleep',
        '__wakeup',
        '__serialize',
        '__unserialize',
    ];

    /**
     * Constructor
     * 
     * @param object $service The service instance to handle
     * @param ContainerInterface|null $container Optional DI container
     */
    public function __construct(object $service, ?ContainerInterface $container = null)
    {
        $this->service = $service;
        $this->serviceClass = get_class($service);
        $this->container = $container;
        $this->cacheServiceMethods();
    }

    /**
     * Handle an RPC method call
     * 
     * @param string $method The method name to call
     * @param array $parameters The method parameters
     * @return mixed The method result
     * @throws RpcException If the method call fails
     */
    public function handleCall(string $method, array $parameters = []): mixed
    {
        if (!$this->hasMethod($method)) {
            throw new MethodNotFoundException($method);
        }

        if ($this->isMethodDenied($method)) {
            throw new MethodNotFoundException($method);
        }

        try {
            $reflectionMethod = $this->getReflectionMethod($method);
            $resolvedParams = $this->resolveParameters($reflectionMethod, $parameters);

            return $reflectionMethod->invokeArgs($this->service, $resolvedParams);
        } catch (RpcException $e) {
            throw $e;
        } catch (\ArgumentCountError $e) {
            throw new InvalidParamsException('Invalid parameter count: ' . $e->getMessage());
        } catch (\TypeError $e) {
            throw new InvalidParamsException('Type error: ' . $e->getMessage());
        } catch (\Throwable $e) {
            throw new RpcException(
                'Method execution failed: ' . $e->getMessage(),
                -32603,
                null,
                0,
                $e
            );
        }
    }

    /**
     * Get available methods for this handler
     * 
     * @return array Array of method names
     */
    public function getMethods(): array
    {
        return array_keys($this->methodCache);
    }

    /**
     * Check if a method is available
     * 
     * @param string $method The method name
     * @return bool
     */
    public function hasMethod(string $method): bool
    {
        return isset($this->methodCache[$method]) && !$this->isMethodDenied($method);
    }

    /**
     * Get method metadata (parameters, return type, etc.)
     * 
     * @param string $method The method name
     * @return array Method metadata
     */
    public function getMethodMetadata(string $method): array
    {
        if (!isset($this->metadataCache[$method])) {
            $this->metadataCache[$method] = $this->extractMethodMetadata($method);
        }

        return $this->metadataCache[$method];
    }

    /**
     * Set allowed method patterns
     * 
     * @param array $patterns Array of method name patterns (supports wildcards)
     * @return void
     */
    public function setAllowedMethods(array $patterns): void
    {
        $this->allowedMethods = $patterns;
    }

    /**
     * Set denied method patterns
     * 
     * @param array $patterns Array of method name patterns (supports wildcards)
     * @return void
     */
    public function setDeniedMethods(array $patterns): void
    {
        $this->deniedMethods = $patterns;
    }

    /**
     * Cache service methods for performance
     * 
     * @return void
     */
    private function cacheServiceMethods(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (!$method->isStatic() && !$method->isConstructor() && !$method->isDestructor()) {
                $this->methodCache[$method->getName()] = $method;
            }
        }
    }

    /**
     * Get reflection method with caching
     * 
     * @param string $method
     * @return \ReflectionMethod
     */
    private function getReflectionMethod(string $method): \ReflectionMethod
    {
        return $this->methodCache[$method];
    }

    /**
     * Resolve method parameters with dependency injection
     * 
     * @param \ReflectionMethod $method
     * @param array $parameters
     * @return array
     * @throws InvalidParamsException
     */
    private function resolveParameters(\ReflectionMethod $method, array $parameters): array
    {
        $reflectionParams = $method->getParameters();
        $resolvedParams = [];

        foreach ($reflectionParams as $param) {
            $paramName = $param->getName();
            $paramIndex = $param->getPosition();

            // Check if parameter is provided by name
            if (array_key_exists($paramName, $parameters)) {
                $resolvedParams[] = $parameters[$paramName];
                continue;
            }

            // Check if parameter is provided by index
            if (array_key_exists($paramIndex, $parameters)) {
                $resolvedParams[] = $parameters[$paramIndex];
                continue;
            }

            // Try dependency injection if container is available
            if ($this->container && $param->getType() && !$param->getType()->isBuiltin()) {
                $typeName = $param->getType()->getName();
                if ($this->container->has($typeName)) {
                    $resolvedParams[] = $this->container->get($typeName);
                    continue;
                }
            }

            // Use default value if available
            if ($param->isDefaultValueAvailable()) {
                $resolvedParams[] = $param->getDefaultValue();
                continue;
            }

            // Parameter is nullable
            if ($param->allowsNull()) {
                $resolvedParams[] = null;
                continue;
            }

            throw new InvalidParamsException("Missing required parameter: {$paramName}");
        }

        return $resolvedParams;
    }

    /**
     * Check if a method is denied
     * 
     * @param string $method
     * @return bool
     */
    private function isMethodDenied(string $method): bool
    {
        // Check explicit denied methods
        foreach ($this->deniedMethods as $pattern) {
            if ($this->matchesPattern($method, $pattern)) {
                return true;
            }
        }

        // If allowed methods are set, check if method is allowed
        if (!empty($this->allowedMethods)) {
            foreach ($this->allowedMethods as $pattern) {
                if ($this->matchesPattern($method, $pattern)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Check if a method name matches a pattern
     * 
     * @param string $method
     * @param string $pattern
     * @return bool
     */
    private function matchesPattern(string $method, string $pattern): bool
    {
        if ($pattern === $method) {
            return true;
        }

        // Simple wildcard support
        if (str_contains($pattern, '*')) {
            // Replace * with .* for regex, but do it before preg_quote
            $escapedPattern = str_replace('*', '__WILDCARD__', $pattern);
            $escapedPattern = preg_quote($escapedPattern, '/');
            $regex = '/^' . str_replace('__WILDCARD__', '.*', $escapedPattern) . '$/';
            return preg_match($regex, $method) === 1;
        }

        return false;
    }

    /**
     * Extract method metadata
     * 
     * @param string $method
     * @return array
     */
    private function extractMethodMetadata(string $method): array
    {
        if (!isset($this->methodCache[$method])) {
            return [];
        }

        $reflection = $this->methodCache[$method];
        $parameters = [];

        foreach ($reflection->getParameters() as $param) {
            $parameters[] = [
                'name' => $param->getName(),
                'type' => $param->getType() ? $param->getType()->getName() : 'mixed',
                'required' => !$param->isOptional(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'nullable' => $param->allowsNull(),
            ];
        }

        return [
            'name' => $method,
            'parameters' => $parameters,
            'return_type' => $reflection->getReturnType() ? $reflection->getReturnType()->getName() : 'mixed',
            'doc_comment' => $reflection->getDocComment() ?: null,
        ];
    }
}
