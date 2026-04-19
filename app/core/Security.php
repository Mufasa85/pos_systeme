<?php

namespace App\Core;

class Security
{
    public static function csrf_tokken(): string
    {
        if (!isset($_SESSION['csrf']) || empty($_SESSION['csrf'])) {
            $token = bin2hex(random_bytes(32));
            self::set_up_csrf_token($token);
        }

        return <<<HTML
  <input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}"/>
HTML;

    }

    private static function set_up_csrf_token(string $tokken): void
    {
        $_SESSION['csrf'] = $tokken;
    }
}
