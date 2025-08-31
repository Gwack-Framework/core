<?php

use Gwack\Http\JsonResponse;
use Gwack\Http\Request;

/**
 * Framework Helper Functions
 *
 * Global functions available in various files and throughout the framework
 */

if (!function_exists('json')) {
    /**
     * Create a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return JsonResponse
     */
    function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }
}

if (!function_exists('request')) {
    /**
     * Get the current request instance
     *
     * @return Request
     */
    function request(): Request
    {
        return $GLOBALS['request'] ?? Request::createFromGlobals();
    }
}

if (!function_exists('context')) {
    /**
     * Get the current context instance
     *
     * @return \Gwack\Core\Context
     */
    function context(): \Gwack\Core\Context
    {
        // First try to get from globals (set during route execution)
        if (isset($GLOBALS['context']) && $GLOBALS['context'] instanceof \Gwack\Core\Context) {
            return $GLOBALS['context'];
        }

        // Fallback: try to get from the app container if available
        if (isset($GLOBALS['app']) && $GLOBALS['app'] instanceof \Gwack\Core\Application) {
            return $GLOBALS['app']->getContainer()->get('context');
        }

        // Last resort: create a minimal context for testing/standalone usage
        throw new \RuntimeException('Context not available. Make sure the application is properly booted.');
    }
}

if (!function_exists('app')) {
    /**
     * Get the application instance
     *
     * @return \Gwack\Core\Application
     */
    function app(): \Gwack\Core\Application
    {
        return context()->app();
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        return context()->config($key, $default);
    }
}

if (!function_exists('session')) {
    /**
     * Get the session manager
     *
     * @return \Gwack\Core\Session\SessionManager
     */
    function session(): \Gwack\Core\Session\SessionManager
    {
        return context()->session();
    }
}

if (!function_exists('abort')) {
    /**
     * Abort with HTTP status code
     *
     * @param int $code
     * @param string $message
     * @return never
     */
    function abort(int $code, string $message = ''): never
    {
        throw new HttpException($code, $message);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect response
     *
     * @param string $url
     * @param int $status
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    function redirect(string $url, int $status = 302): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return new \Symfony\Component\HttpFoundation\RedirectResponse($url, $status);
    }
}

if (!function_exists('response')) {
    /**
     * Create a basic response
     *
     * @param string $content
     * @param int $status
     * @param array $headers
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function response(string $content = '', int $status = 200, array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        return new \Symfony\Component\HttpFoundation\Response($content, $status, $headers);
    }
}

if (!function_exists('view')) {
    /**
     * Render a view (placeholder for future template engine integration)
     *
     * @param string $template
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function view(string $template, array $data = []): \Symfony\Component\HttpFoundation\Response
    {
        // This is a placeholder - in the future this will integrate with a template engine
        $content = "<!-- Template: $template -->\n" . json_encode($data, JSON_PRETTY_PRINT);
        return response($content);
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    function logger(string $message, string $level = 'info'): void
    {
        // Simple file-based logging for now
        $logFile = '/tmp/gwack.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cache')) {
    /**
     * Simple cache function
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    function cache(string $key, mixed $value = null): mixed
    {
        static $cache = [];

        if ($value !== null) {
            $cache[$key] = $value;
            return $value;
        }

        return $cache[$key] ?? null;
    }
}


if (!function_exists('dd')) {
    /**
     * Dump and die
     *
     * @param mixed ...$vars
     * @return void
     */
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        exit(1);
    }
}

if (!function_exists('validate')) {
    /**
     * Validate request data using the validation system
     *
     * @param \Gwack\Http\Request $request
     * @param array $rules
     * @return array
     */
    function validate(\Gwack\Http\Request $request, array $rules): array
    {
        return context()->validate($request, $rules);
    }
}

if (!function_exists('validator')) {
    /**
     * Get the request validator
     *
     * @return \Gwack\Http\Validation\RequestValidator
     */
    function validator(): \Gwack\Http\Validation\RequestValidator
    {
        return context()->validator();
    }
}

if (!function_exists('rules')) {
    /**
     * Get the rule executor
     *
     * @return \Gwack\Http\Validation\RuleExecutor
     */
    function rules(): \Gwack\Http\Validation\RuleExecutor
    {
        return context()->rules();
    }
}
