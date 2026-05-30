<?php
namespace App;

class Router
{
    private array $routes = [];

    public function get(string $uri, string $controller, string $method, bool $auth = false): void
    {
        $this->addRoute('GET', $uri, $controller, $method, $auth);
    }

    public function post(string $uri, string $controller, string $method, bool $auth = false): void
    {
        $this->addRoute('POST', $uri, $controller, $method, $auth);
    }

    public function delete(string $uri, string $controller, string $method, bool $auth = false): void
    {
        $this->addRoute('DELETE', $uri, $controller, $method, $auth);
    }

    private function addRoute(string $verb, string $uri, string $controller, string $method, bool $auth): void
    {
        $this->routes[] = compact('verb', 'uri', 'controller', 'method', 'auth');
    }

    public function dispatch(string $verb, string $rawUri): void
    {
        // 去掉 query string
        $uri = parse_url($rawUri, PHP_URL_PATH) ?: '/';
        // 去掉尾部斜杠（除了根路径）
        if ($uri !== '/') $uri = rtrim($uri, '/');

        // 遍历路由
        foreach ($this->routes as $route) {
            if ($route['verb'] !== $verb) continue;
            $params = $this->matchUri($route['uri'], $uri);
            if ($params === false) continue;

            // 鉴权
            if ($route['auth']) {
                Middleware::auth();
            }

            // 实例化 Controller 并调用
            $className = '\\App\\' . $route['controller'];
            if (!class_exists($className)) {
                http_response_code(500);
                die("Controller 不存在: {$className}");
            }
            $instance = new $className();
            call_user_func_array([$instance, $route['method']], $params);
            return;
        }

        // 404
        http_response_code(404);
        die('404 Not Found');
    }

    /**
     * 匹配 URI 模式（支持 /path/{name:regex} 占位符）
     * 返回匹配到的参数数组，失败返回 false
     */
    private function matchUri(string $pattern, string $uri): array|false
    {
        // 转换模式为 regex
        $regex = preg_replace_callback('#\{(\w+)(?::([^}]+))?\}#', function ($m) {
            $name = $m[1];
            $re   = $m[2] ?? '[^/]+';
            return '(?P<' . $name . '>' . $re . ')';
        }, $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // 只保留命名捕获组
            $params = [];
            foreach ($matches as $k => $v) {
                if (is_string($k)) $params[] = $v;
            }
            return $params;
        }
        return false;
    }
}
