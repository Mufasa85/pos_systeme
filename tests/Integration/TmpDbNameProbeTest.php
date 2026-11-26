<?php

namespace Tests\Integration;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

class TmpDbNameProbeTest extends TestCase
{
    public function testQuelleBaseEstUtilisee(): void
    {
        fwrite(STDERR, "\n=== getenv(DB_NAME) = " . var_export(getenv('DB_NAME'), true) . "\n");
        fwrite(STDERR, "=== constant DB_NAME  = " . var_export(DB_NAME, true) . "\n");
        fwrite(STDERR, "=== base réelle       = " . var_export(Database::getInstance()->fetch('SELECT DATABASE() AS d')['d'], true) . "\n");
        $this->assertTrue(true);
    }
}
