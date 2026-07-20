<?php

class Router {
    private $routes = [];

    public function add($method, $path, $handler) {
        $this->routes[] = compact('method', 'path', 'handler');
    }

    public function run() {
        $method = $_SERVER['REQUEST_METHOD'];

        // Host strips PATH_INFO, so we use query-string routing
        $uri = $_GET['route'] ?? '/';

        // Normalize
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            $pattern = "@^" . preg_replace('/\{(\w+)\}/', '(?P<\1>\d+)', $route['path']) . "$@";

            if ($method === $route['method'] && preg_match($pattern, $uri, $matches)) {
                return call_user_func($route['handler'], $matches);
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
}
