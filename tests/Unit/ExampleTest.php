<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testBasicAssertion(): void
    {
        $this->assertTrue(true);
    }

    public function testApplicationConstantsAreDefined(): void
    {
        $this->assertTrue(defined('BASE_PATH'));
    }
}
