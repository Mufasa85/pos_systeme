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
            // Pas de base path - routes sont relatives à la racine du domaine
        }
        return self::$router;
    }
}
