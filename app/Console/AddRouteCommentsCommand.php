<?php

namespace Modules\Core\Console;

use Illuminate\Support\Str;
use Modules\Core\Overrides\Command;
use Illuminate\Support\Facades\Route;

class AddRouteCommentsCommand extends Command
{
    protected $signature = 'route:add-comments';
    protected $description = 'Add route comments to controller methods <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    private const string ROUTE_COMMENT_MARKER = '@route-comment';

    public function handle()
    {
        $routes = Route::getRoutes();
        $processed_controllers = [];
        $method_routes = [];

        // First collect all routes for each method
        /** @var \Illuminate\Routing\Route $route */
        foreach ($routes as &$route) {
            $action = $route->getActionName();
            if (!Str::startsWith($action, ['Modules\\', 'App\\'])) {
                continue;
            }

            if (!Str::contains((string) $action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', (string) $action);

            if (!class_exists($controller)) {
                continue;
            }


            // $key = $controller . '@' . $method;
            if (!isset($method_routes[$action])) {
                $method_routes[$action] = [];
            }
            $method_routes[$action][] = $route;
        }

        // Then process each method with all its routes
        foreach ($method_routes as $key => &$routes) {
            [$controller, $method] = explode('@', $key);

            $reflection_class = new \ReflectionClass($controller);

            if (!$reflection_class->hasMethod($method)) {
                continue;
            }

            $reflection_method = $reflection_class->getMethod($method);

            // Check if the method is inherited
            if ($reflection_method->getDeclaringClass()->getName() !== $controller) {
                $this->warn("Method {$method} in {$controller} is inherited from {$reflection_method->getDeclaringClass()->getName()}. Skipping...");
                continue;
            }

            $file_path = $reflection_class->getFileName();

            if (!file_exists($file_path)) {
                continue;
            }

            $route_comments = [];
            foreach ($routes as &$route) {
                $route_info = $this->getRouteInfo($route);
                $route_comments[] = $route_info;
            }

            $comment = $this->generateComment($route_comments);
            $this->addCommentToMethod($file_path, $method, $comment);

            if (!in_array($controller, $processed_controllers)) {
                $this->info("Processed controller: {$controller}");
                $processed_controllers[] = $controller;
            }
        }

        $this->info('Route comments have been added successfully!');
    }

    private function getRouteInfo($route): array
    {
        return [
            'methods' => $route->methods(),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'middleware' => $route->middleware(),
        ];
    }

    private function generateComment(array $route_comments): string
    {
        $comment = "\t/**\n\t * " . self::ROUTE_COMMENT_MARKER . "\n";

        foreach ($route_comments as &$route_info) {
            $comment .= "\t * Route(path: '{$route_info['uri']}', name: '{$route_info['name']}', methods: [" . implode(', ', $route_info['methods']) . "], middleware: [" . implode(', ', $route_info['middleware']) . "])\n";
        }

        return $comment . "\t */";
    }

    private function addCommentToMethod(string $file_path, string $method_name, string $comment): void
    {
        $content = file_get_contents($file_path);
        $lines = explode("\n", $content);
        $method_found = false;
        $method_start_line = 0;
        $new_content = [];
        $has_existing_comment = false;
        $existing_comment_start = 0;
        $existing_comment_end = 0;

        $counter = count($lines);

        // First find the method and its existing comment
        for ($i = 0; $i < $counter; $i++) {
            $line = $lines[$i];

            // If we find the method
            if (str_contains($line, "function {$method_name}")) {
                $method_found = true;
                $method_start_line = $i;

                // Check if there is an existing comment
                for ($j = $i - 1; $j >= 0; $j--) {
                    if (str_contains($lines[$j], '/**')) {
                        $has_existing_comment = true;
                        $existing_comment_start = $j;
                        // Find the end of the comment
                        for ($k = $j; $k < $i; $k++) {
                            if (str_contains($lines[$k], '*/')) {
                                $existing_comment_end = $k;
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }

        if (!$method_found) {
            return;
        }

        $counter = count($lines);

        // Build the new content
        for ($i = 0; $i < $counter; $i++) {
            if ($has_existing_comment) {
                // Check if the existing comment is a route comment
                $existing_comment = implode("\n", array_slice($lines, $existing_comment_start, $existing_comment_end - $existing_comment_start + 1));
                if (str_contains($existing_comment, self::ROUTE_COMMENT_MARKER)) {
                    // Skip the existing comment lines
                    if ($i >= $existing_comment_start && $i <= $existing_comment_end) {
                        continue;
                    }
                } elseif ($i >= $existing_comment_start && $i <= $existing_comment_end) {
                    // If it's not a route comment, keep it
                    $new_content[] = $lines[$i];
                    continue;
                }
            }

            if ($i === $method_start_line) {
                $new_content[] = $comment;
            }
            $new_content[] = $lines[$i];
        }

        file_put_contents($file_path, implode("\n", $new_content));
    }
}
