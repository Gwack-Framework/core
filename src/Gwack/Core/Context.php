<?php

namespace Gwack\Core;

use Gwack\Container\Container;
use Gwack\Core\Session\SessionManager;
use Gwack\Http\Validation\RequestValidator;
use Gwack\Http\Validation\RuleExecutor;
use Gwack\Http\Request;

/**
 * Application Context
 * 
 * Provides unified access to all application services and components.
 * This class acts as a service locator for route handlers and other
 * application components.
 * 
 * @package Gwack\Core
 */
class Context
{
    private Container $container;

    /**
     * Context constructor
     * 
     * @param Container $container The DI container instance
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get the session manager
     * 
     * @return SessionManager
     */
    public function session(): SessionManager
    {
        return $this->container->get('session');
    }

    /**
     * Get the application instance
     * 
     * @return Application
     */
    public function app(): Application
    {
        return $this->container->get('app');
    }

    /**
     * Get configuration value
     * 
     * @param string|null $key Configuration key (null for all config)
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        $config = $this->container->get('config');

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }

    /**
     * Get a service from the container
     * 
     * @param string $abstract Service identifier
     * @return mixed
     */
    public function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    /**
     * Check if a service exists in the container
     * 
     * @param string $abstract Service identifier
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return $this->container->has($abstract);
    }

    /**
     * Get the container instance
     * 
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Get the request validator
     * 
     * @return RequestValidator
     */
    public function validator(): RequestValidator
    {
        return $this->container->get('validator');
    }

    /**
     * Get the rule executor
     * 
     * @return RuleExecutor
     */
    public function rules(): RuleExecutor
    {
        return $this->container->get('validation.rule_executor');
    }

    /**
     * Validate request data
     * 
     * @param Request $request The request to validate
     * @param array $rules Validation rules
     * @return array Validation result
     */
    public function validate(Request $request, array $rules): array
    {
        return $this->validator()->validate($request, $rules);
    }
}
