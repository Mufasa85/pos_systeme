<?php

namespace App\Core;

// app/core/Router.php

class Router
{
    private $routes = [
        'GET'  => [],
        'POST' => []
    ];

    public function get($uri, $action)
    {
        $this->routes['GET'][$uri] = $action;
    }

    public function post($uri, $action)
    {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch($methode, $uri)
    {
        // Nettoyer l'URI, ex: enlever les query strings
        $uri = parse_url($uri, PHP_URL_PATH);
        // Retirer le dossier de base si l'app est dans un sous dossier
        // (WAMP default: /pos/public/)
        $base = '/pos/public';
        if (strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        if ($uri == '') {
            $uri = '/';
        }

        if (array_key_exists($uri, $this->routes[$methode])) {
            $action = $this->routes[$methode][$uri];
            // Format "Controller@method"
            list($controller, $method) = explode('@', $action);

            // Require Controller file
            require_once BASE_PATH . 'app/controllers/' . $controller . '.php';

            $controllerInstance = new $controller();
            $controllerInstance->$method();
        } else {
            // 404
            http_response_code(404);
            if (strpos($uri, '/api/') === 0) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'API Endpoint non trouve']);
            } else {
                echo "404 Page non trouvee";
            }
        }
    }
}
