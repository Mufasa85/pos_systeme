<?php


define('DB_HOST', false !== ($v = getenv('DB_HOST')) ? $v : 'localhost');
define('DB_USER', false !== ($v = getenv('DB_USER')) ? $v : 'root');
define('DB_PASS', false !== ($v = getenv('DB_PASS')) ? $v : '');
define('DB_NAME', false !== ($v = getenv('DB_NAME')) ? $v : 'pos_system');

define('APP_URL', 'http://localhost/pos_systeme/');
define('BASE_PATH', dirname(__DIR__) . '/');
