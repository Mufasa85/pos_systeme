<?php

namespace Tests\Functional;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;

class LoginFlowTest extends TestCase
{
    private function client(): Client
    {
        return new Client([
            'base_uri' => getenv('APP_URL') ?: 'http://localhost/ArcaneCore_projet/pos_systeme/public/',
            'timeout'  => 2,
            'http_errors' => false,
        ]);
    }

    public function testLoginPageLoads(): void
    {
        try {
            $response = $this->client()->get('');
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsStringIgnoringCase('body', (string) $response->getBody());
        } catch (ConnectException $e) {
            $this->markTestSkipped('Local server not running: ' . $e->getMessage());
        }
    }
}
