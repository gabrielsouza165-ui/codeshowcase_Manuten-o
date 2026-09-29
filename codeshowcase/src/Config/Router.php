<?php

namespace App\Config;

class Router
{
    private array $routes = [];

    public function get($uri,$callback)
    {
        $this->routes['GET'][$uri]
            = $callback;
    }

    public function post($uri,$callback)
    {
        $this->routes['POST'][$uri]
            = $callback;
    }

    public function dispatch(
        $uri,
        $method
    )
    {
        $uri = parse_url((string) $uri, PHP_URL_PATH) ?? (string) $uri;
        // Normaliza trailing slash (exceto raiz) para /projetos/ => /projetos
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
            if ($uri === '') $uri = '/';
        }

        if(
            !isset(
                $this->routes[$method][$uri]
            )
        ){
            http_response_code(404);
            echo '404 - Página não encontrada';
            return;
        }

        [$class,$function]
            = $this->routes[$method][$uri];

        $controller = new $class();

        $controller->$function();
    }
}