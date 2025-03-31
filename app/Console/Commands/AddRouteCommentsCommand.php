<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class AddRouteCommentsCommand extends Command
{
    protected $signature = 'route:add-comments';
    protected $description = 'Add route comments to controller methods';

    private const ROUTE_COMMENT_MARKER = '@route-comment';

    public function handle()
    {
        $routes = Route::getRoutes();
        $processed_controllers = [];
        $method_routes = [];

        // First collect all routes for each method
        foreach ($routes as $route) {
            $action = $route->getActionName();

            if (!str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action);

            if (!class_exists($controller)) {
                continue;
            }

            $key = $controller . '@' . $method;
            if (!isset($method_routes[$key])) {
                $method_routes[$key] = [];
            }
            $method_routes[$key][] = $route;
        }

        // Poi processiamo ogni metodo con tutte le sue rotte
        foreach ($method_routes as $key => $routes) {
            [$controller, $method] = explode('@', $key);

            $reflection_class = new \ReflectionClass($controller);

            if (!$reflection_class->hasMethod($method)) {
                continue;
            }

            $reflection_method = $reflection_class->getMethod($method);

            // Verifichiamo se il metodo è ereditato
            if ($reflection_method->getDeclaringClass()->getName() !== $controller) {
                $this->warn("Method {$method} in {$controller} is inherited from {$reflection_method->getDeclaringClass()->getName()}. Skipping...");
                continue;
            }

            $file_path = $reflection_class->getFileName();

            if (!file_exists($file_path)) {
                continue;
            }

            $route_comments = [];
            foreach ($routes as $route) {
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
        $comment = "/**\n";
        $comment .= " * " . self::ROUTE_COMMENT_MARKER . "\n\n";

        if (count($route_comments) > 1) {
            $comment .= " * Routes:\n";
            foreach ($route_comments as $route_info) {
                $comment .= " * - " . implode('|', $route_info['methods']) . " {$route_info['uri']}\n";
                if ($route_info['name']) {
                    $comment .= " *   Name: {$route_info['name']}\n";
                }
                if (!empty($route_info['middleware'])) {
                    $comment .= " *   Middleware: " . implode(', ', $route_info['middleware']) . "\n";
                }
            }
        } else {
            $route_info = $route_comments[0];
            $comment .= " * Route: " . implode('|', $route_info['methods']) . " {$route_info['uri']}\n";
            if ($route_info['name']) {
                $comment .= " * Name: {$route_info['name']}\n";
            }
            if (!empty($route_info['middleware'])) {
                $comment .= " * Middleware: " . implode(', ', $route_info['middleware']) . "\n";
            }
        }

        $comment .= " */";

        return $comment;
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

        // Prima troviamo il metodo e il suo commento esistente
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            // Se troviamo il metodo
            if (str_contains($line, "function {$method_name}")) {
                $method_found = true;
                $method_start_line = $i;

                // Controlliamo se c'è un commento esistente
                for ($j = $i - 1; $j >= 0; $j--) {
                    if (str_contains($lines[$j], '/**')) {
                        $has_existing_comment = true;
                        $existing_comment_start = $j;
                        // Cerchiamo la fine del commento
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

        // Costruiamo il nuovo contenuto
        for ($i = 0; $i < count($lines); $i++) {
            if ($has_existing_comment) {
                // Verifichiamo se il commento esistente è un commento di rotta
                $existing_comment = implode("\n", array_slice($lines, $existing_comment_start, $existing_comment_end - $existing_comment_start + 1));
                if (str_contains($existing_comment, self::ROUTE_COMMENT_MARKER)) {
                    // Saltiamo le righe del commento esistente
                    if ($i >= $existing_comment_start && $i <= $existing_comment_end) {
                        continue;
                    }
                } else {
                    // Se non è un commento di rotta, lo manteniamo
                    if ($i >= $existing_comment_start && $i <= $existing_comment_end) {
                        $new_content[] = $lines[$i];
                        continue;
                    }
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
