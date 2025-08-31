<?php

namespace Gwack\Core\Compiler;

/**
 * Route Compiler
 *
 * Compiles file-based routes into optimized PHP code for production.
 * This is where the magic happens - converting our framework syntax
 * into highly optimized production code.
 *
 * @package Gwack\Core\Compiler
 */
class RouteCompiler
{
    private string $basePath;
    private string $serverPath;
    private string $distPath;
    private array $config;

    /**
     * RouteCompiler constructor
     *
     * @param string $basePath Application base path
     * @param array $config Compiler configuration
     */
    public function __construct(string $basePath, array $config = [])
    {
        $this->basePath = rtrim($basePath, '/');
        $this->serverPath = $this->basePath . '/server';
        // Write compiled artifacts (routes.php, types.ts) to .gwack for dev friendliness
        $this->distPath = $this->basePath . '/.gwack';
        $this->config = $config;
    }

    /**
     * Compile all routes for production
     *
     * @return void
     */
    public function compile(): void
    {
        if (!is_dir($this->serverPath)) {
            return;
        }

        // Ensure dist directory exists
        if (!is_dir($this->distPath)) {
            mkdir($this->distPath, 0755, true);
        }

        $routes = $this->discoverRoutes();
        $this->generateCompiledRoutes($routes);
        $this->generateTypeDefinitions($routes);
    }

    /**
     * Discover all route files
     *
     * @return array
     */
    private function discoverRoutes(): array
    {
        $routes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->serverPath)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($this->serverPath, '', $file->getPathname());
                $routePath = $this->convertFilePathToRoute($relativePath);

                $routes[] = [
                    'file' => $file->getPathname(),
                    'path' => $routePath,
                    'relative' => $relativePath,
                ];
            }
        }

        return $routes;
    }

    /**
     * Convert file path to route pattern
     *
     * @param string $filePath
     * @return string
     */
    private function convertFilePathToRoute(string $filePath): string
    {
        // Remove leading slash and .php extension
        $route = ltrim($filePath, '/');
        $route = preg_replace('/\.php$/', '', $route);

        // Convert /index to /
        $route = preg_replace('/\/index$/', '', $route);

        // Convert [param] to {param} for our router
        $route = preg_replace('/\[([^\]]+)\]/', '{$1}', $route);

        return '/' . ltrim($route, '/');
    }

    /**
     * Generate compiled route file
     *
     * @param array $routes
     * @return void
     */
    private function generateCompiledRoutes(array $routes): void
    {
        $output = "<?php\n\n";
        $output .= "// Auto-generated compiled routes\n";
        $output .= "// DO NOT EDIT - This file is generated automatically\n\n";

        $output .= "return [\n";

        foreach ($routes as $route) {
            $routeCode = $this->compileRouteFile($route['file']);
            $output .= "    '{$route['path']}' => " . $routeCode . ",\n";
        }

        $output .= "];\n";

        file_put_contents($this->distPath . '/routes.php', $output);
    }

    /**
     * Compile a single route file
     *
     * @param string $filePath
     * @return string
     */
    private function compileRouteFile(string $filePath): string
    {
        $content = file_get_contents($filePath);

        // Extract the defineRoute closure content
        if (preg_match('/defineRoute\s*\(\s*function\s*\([^)]*\)\s*{(.+)}\s*\)\s*;/s', $content, $matches)) {
            $routeBody = trim($matches[1]);

            // Optimize the route code
            $optimized = $this->optimizeRouteCode($routeBody);

            return "function(\$context, \$request) { $optimized }";
        }

        // Fallback - return a closure that includes the original file at runtime
        // Use single quotes to avoid PHP interpolating $ in generated code
        $inc = var_export($filePath, true);
        return 'function($context, $request) { return include ' . $inc . '; }';
    }

    /**
     * Optimize route code for production
     *
     * @param string $code
     * @return string
     */
    private function optimizeRouteCode(string $code): string
    {
        // Remove debug statements
        $code = preg_replace('/\b(dump|dd|var_dump|print_r)\s*\([^)]*\)\s*;?/', '', $code);

        // Optimize session calls
        $code = str_replace('$context->session()', '$_SESSION', $code);

        // Optimize simple validations
        $code = preg_replace('/\$request->validate\(\s*\[\s*([^]]+)\s*\]\s*\)/', 'validate($request, [$1])', $code);

        return $code;
    }

    /**
     * Generate TypeScript type definitions
     *
     * @param array $routes
     * @return void
     */
    private function generateTypeDefinitions(array $routes): void
    {
        $output = "// Auto-generated API type definitions\n";
        $output .= "// DO NOT EDIT - This file is generated automatically\n\n";

        $output .= "export interface ApiRoutes {\n";

        foreach ($routes as $route) {
            $routeName = $this->routePathToName($route['path']);
            $output .= "  '{$routeName}': {\n";
            $output .= "    path: '{$route['path']}',\n";
            $output .= "    methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],\n";

            // Analyze route parameters
            $params = $this->extractRouteParameters($route['path']);
            if (!empty($params)) {
                $output .= "    params: {\n";
                foreach ($params as $param) {
                    $output .= "      {$param}: string;\n";
                }
                $output .= "    },\n";
            }

            $output .= "  },\n";
        }

        $output .= "}\n\n";

        // Add fetch helper type
        $output .= "export type ApiFetch = {\n";
        foreach ($routes as $route) {
            $routeName = $this->routePathToName($route['path']);
            $output .= "  '{$routeName}': (params?: any) => Promise<any>;\n";
        }
        $output .= "}\n";

        file_put_contents($this->distPath . '/types.ts', $output);
    }

    /**
     * Convert route path to TypeScript-friendly name
     *
     * @param string $path
     * @return string
     */
    private function routePathToName(string $path): string
    {
        // Convert /posts/{id} to posts.show
        $name = trim($path, '/');
        $name = str_replace(['/', '{', '}'], ['.', '', ''], $name);

        // Handle index routes
        if (empty($name)) {
            return 'index';
        }

        return $name;
    }

    /**
     * Extract route parameters from path
     *
     * @param string $path
     * @return array
     */
    private function extractRouteParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }
}
