<?php

namespace App\Core;

use function Sodium\bin2hex;

class Security
{
    public static function csrf_tokken(): string
    {
        $tokken = bin2hex(random_bytes(32));
        $db = \App\Core\Database::getInstance();

        $db->query("INSERT INTO tokkens_csrf(tokken,created_at,expired_at) VALUE(?,NOW(),NOW() + INTERVAL 3 MINUTE)", [$tokken]);

        return <<<HTML
  <input type="hidden" name="csrf_token" value="{$tokken}"/>
HTML;

    }
}
