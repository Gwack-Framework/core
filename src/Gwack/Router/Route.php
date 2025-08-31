<?php

namespace Gwack\Router;

use Gwack\Router\Interfaces\RouteInterface;

/**
 * Class Route
 *
 * Represents a single route in the routing system with optimized path matching
 *
 * @package Gwack\Router
 */
class Route implements RouteInterface
{
    /**
     * @var string The HTTP method for this route
     */
    private string $method;

    /**
     * @var string The original path pattern
     */
    private string $path;

    /**
     * @var callable The handler function
     */
    private $handler;

    /**
     * @var string|null The compiled regex pattern
     */
    private ?string $compiledPattern = null;

    /**
     * @var array Parameter names extracted from the path
     */
    private array $parameterNames = [];

    /**
     * @var bool Whether this is a static route (no dynamic segments)
     */
    private bool $isStatic = false;

    /**
     * @var array Additional options for the route
     */
    private array $options;

    /**
     * @var string Static path prefix for quick rejection
     */
    private string $staticPrefix = '';

    /**
     * @var array Custom parameter patterns [paramName => regex]
     */
    private array $parameterPatterns = [];

    /**
     * Route constructor
     *
     * @param string $method HTTP method
     * @param string $path Path pattern
     * @param callable|array $handler Route handler
     * @param array $options Additional options
     */
    public function __construct(string $method, string $path, callable|array $handler, array $options = [])
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->options = $options;

        // Compile the route immediately for performance
        $this->compile();
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandler(): callable|array
    {
        return $this->handler;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(string $path): array|false
    {
        // Fast path for static routes
        if ($this->isStatic) {
            return $path === $this->path ? [] : false;
        }

        // Early rejection for obvious mismatches
        if ($this->staticPrefix !== '' && strncmp($path, $this->staticPrefix, strlen($this->staticPrefix)) !== 0) {
            return false;
        }

        // Use regex for dynamic routes
        $result = @preg_match($this->compiledPattern, $path, $matches);

        if ($result === false) {
            // Handle regex error
            return false;
        } elseif ($result === 0) {
            // No match
            return false;
        }

        // Skip the first match (full string)
        array_shift($matches);

        $params = [];
        foreach ($this->parameterNames as $i => $name) {
            $params[$name] = $matches[$i] ?? null;
        }

        return $params;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompiledPattern(): string
    {
        return $this->compiledPattern ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterNames(): array
    {
        return $this->parameterNames;
    }

    /**
     * Get the original path pattern
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->path;
    }

    /**
     * Get parameter constraints for this route
     *
     * @return array
     */
    public function getParameterConstraints(): array
    {
        return $this->parameterPatterns;
    }

    /**
     * {@inheritdoc}
     */
    public function compile(): void
    {
        // Check if this is a static route (no parameters)
        if (strpos($this->path, '{') === false) {
            $this->isStatic = true;
            $this->compiledPattern = '#^' . preg_quote($this->path, '#') . '$#';
            return;
        }

        $this->isStatic = false;
        $this->parameterNames = [];

        // Store static prefix for quick filtering
        $prefix = strstr($this->path, '{', true);
        $this->staticPrefix = $prefix !== false ? $prefix : '';

        // First extract parameter names
        preg_match_all('/{([^:}]+)(?::[^}]+)?}/', $this->path, $matches);
        $this->parameterNames = $matches[1];

        // Build pattern by processing each part separately
        $pattern = '';
        $pos = 0;
        $path = $this->path;

        while (($start = strpos($path, '{', $pos)) !== false) {
            // Add the static part before the parameter
            $staticPart = substr($path, $pos, $start - $pos);
            $pattern .= preg_quote($staticPart, '#');

            // Find the matching closing brace (handle nested braces in regex)
            $braceCount = 1;
            $end = $start + 1;
            while ($end < strlen($path) && $braceCount > 0) {
                if ($path[$end] === '{') {
                    $braceCount++;
                } elseif ($path[$end] === '}') {
                    $braceCount--;
                }
                if ($braceCount > 0) {
                    $end++;
                }
            }

            if ($braceCount > 0) {
                break; // Malformed path - unmatched braces
            }

            // Extract parameter definition (between { and })
            $paramDef = substr($path, $start + 1, $end - $start - 1);

            // Check if it has a regex pattern
            if (strpos($paramDef, ':') !== false) {
                [$name, $regex] = explode(':', $paramDef, 2);
                $pattern .= '(' . $regex . ')';
            } else {
                $name = $paramDef;
                // If the parameter has a custom pattern from where()
                if (isset($this->parameterPatterns[$name])) {
                    $pattern .= '(' . $this->parameterPatterns[$name] . ')';
                } else {
                    // Default pattern
                    $pattern .= '([^/]+)';
                }
            }

            $pos = $end + 1;
        }

        // Add any remaining static part
        if ($pos < strlen($path)) {
            $pattern .= preg_quote(substr($path, $pos), '#');
        }

        // Create the final pattern
        $this->compiledPattern = '#^' . $pattern . '$#';

        // Final validation
        if (@preg_match($this->compiledPattern, '') === false) {
            // Something is wrong with the pattern, use a fallback
            $pattern = preg_replace('/{[^}]+}/', '([^/]+)', $this->path);
            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace(['\(', '\)'], ['(', ')'], $pattern);
            $this->compiledPattern = '#^' . $pattern . '$#';
        }
    }

    /**
     * Check if this route is static (has no dynamic parameters)
     *
     * @return bool
     */
    public function isStatic(): bool
    {
        return $this->isStatic;
    }

    /**
     * Set a custom regex pattern for a route parameter
     *
     * @param string $param Parameter name
     * @param string $pattern Regex pattern without delimiters
     * @return self For method chaining
     */
    public function where(string $param, string $pattern): self
    {
        $this->setParameterPattern($param, $pattern);
        return $this;
    }

    /**
     * Set multiple patterns at once
     *
     * @param array $patterns [param => regex]
     * @return self For method chaining
     */
    public function whereMultiple(array $patterns): self
    {
        foreach ($patterns as $param => $pattern) {
            $this->setParameterPattern($param, $pattern);
        }
        return $this;
    }

    /**
     * Set parameter pattern (internal method used by RouteCollection)
     *
     * @param string $param Parameter name
     * @param string $pattern Regex pattern
     * @return void
     */
    public function setParameterPattern(string $param, string $pattern): void
    {
        $this->parameterPatterns[$param] = $pattern;
        $this->compile(); // Recompile the route with the new pattern
    }

    /**
     * Get the options for this route
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
