<?php

namespace App\controllers;

class Controller
{
    private static $controller;

    private static function instance(): self
    {
        if (is_null(self::$controller)) {
            self::$controller = new self();
        }
        return self::$controller;
    }

    protected function sanitaze(string $input): string
    {
        return strip_tags(htmlspecialchars($input));
    }
    public static function status(int $status)
    {
        \http_response_code($status);
        return self::instance();
    }

    public static function json($array)
    {
        header("Content-Type:application/json");
        echo json_encode($array, JSON_PRETTY_PRINT);
    }

    public function inputs()
    {
        $datas = \file_get_contents('php://input');
        return $datas;
    }

    //public function create()
    //{
    //}
    //public function delete()
    //{
    //}
    //public function index()
    //{
    //}
    //public function update()
    //{
    //}
}
