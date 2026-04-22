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
        }
        return self::$router;
    }
}
 
