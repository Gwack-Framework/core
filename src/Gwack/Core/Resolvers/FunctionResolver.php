<?php

namespace Gwack\Core\Resolvers;

use Gwack\Container\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Gwack\Core\Context;
use Gwack\Http\Request;

/**
 * Function Resolver
 *
 * Registers framework utility functions in the container system
 * This allows route handlers to access framework functions through dependency injection
 */
class FunctionResolver
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->registerFunctions();
    }

    /**
     * Register all framework functions in the container
     *
     * @return void
     */
    private function registerFunctions(): void
    {
        $this->container->bindFunction('json', $this->json(...));
        $this->container->bindFunction('request', $this->request(...));
        $this->container->bindFunction('context', $this->context(...));
        $this->container->bindFunction('app', $this->app(...));
        $this->container->bindFunction('config', $this->config(...));
        $this->container->bindFunction('session', $this->session(...));
        $this->container->bindFunction('abort', $this->abort(...));
        $this->container->bindFunction('redirect', $this->redirect(...));
        $this->container->bindFunction('response', $this->response(...));
        $this->container->bindFunction('logger', $this->logger(...));
        $this->container->bindFunction('cache', $this->cache(...));
        $this->container->bindFunction('validate', $this->validate(...));
    }

    /**
     * Get all framework functions from the container
     *
     * @return array<string, callable>
     */
    public function getFunctions(): array
    {
        return $this->container->getFunctions();
    }

    /**
     * Create a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return JsonResponse
     */
    public function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Get the current request instance
     *
     * @return Request
     */
    public function request(): Request
    {
        return $this->container->get(Request::class);
    }

    /**
     * Get the current context instance
     *
     * @return Context
     */
    public function context(): Context
    {
        return $this->container->get('context');
    }

    /**
     * Get the application instance
     *
     * @return \Gwack\Core\Application
     */
    public function app(): \Gwack\Core\Application
    {
        return $this->container->get('app');
    }

    /**
     * Get configuration value
     *
     * @param string|null $key
     * @param mixed $default
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
     * Get session manager
     *
     * @return \Gwack\Core\Session\SessionManager
     */
    public function session(): \Gwack\Core\Session\SessionManager
    {
        return $this->container->get('session');
    }

    /**
     * Abort with HTTP status
     *
     * @param int $status
     * @param string|null $message
     * @return JsonResponse
     */
    public function abort(int $status, ?string $message = null): JsonResponse
    {
        $data = ['error' => true, 'status' => $status];

        if ($message) {
            $data['message'] = $message;
        }

        return new JsonResponse($data, $status);
    }

    /**
     * Create a redirect response
     *
     * @param string $url
     * @param int $status
     * @return RedirectResponse
     */
    public function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Create a custom response
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(mixed $content, int $status = 200, array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        return new \Symfony\Component\HttpFoundation\Response($content, $status, $headers);
    }

    /**
     * Log a message
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    public function logger(string $message, string $level = 'info'): void
    {
        // Simple file-based logging for now
        $logFile = '/tmp/gwack.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Simple cache function
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function cache(string $key, mixed $value = null): mixed
    {
        static $cache = [];

        if ($value !== null) {
            $cache[$key] = $value;
            return $value;
        }

        return $cache[$key] ?? null;
    }

    /**
     * Simple validation function
     *
     * @param array $data
     * @param array $rules
     * @return array
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            if ($rule === 'required' && empty($value)) {
                $errors[$field] = "The {$field} field is required.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
