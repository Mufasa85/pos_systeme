<?php

namespace App;

use AltoRouter;

class App
{
    private static $router;

    public static function getInstanceRouter(): AltoRouter
    {
        if (self::$router == null) {
            self::$router = new AltoRouter();

            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
            $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

            if ($basePath !== '') {
                self::$router->setBasePath($basePath);
            }
        }
        return self::$router;
    }

    /**
     * Force le base path du routeur.
     *
     * UTILISEE PAR LES TESTS UNIQUEMENT : en CLI, `SCRIPT_NAME` pointe sur
     * le binaire de PHPUnit (`vendor/bin/phpunit`), ce qui donne un base path
     * non vide et empeche toute correspondance d'URL.
     *
     * @param string $basePath Base path normalise, ou '' pour aucun prefixe.
     */
    public static function setRouterBasePath(string $basePath): void
    {
        $router = self::getInstanceRouter();
        $router->setBasePath(rtrim(str_replace('\\', '/', $basePath), '/'));
    }
}
