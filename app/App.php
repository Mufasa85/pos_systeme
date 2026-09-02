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
}
