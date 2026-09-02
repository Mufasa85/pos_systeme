<?php

namespace Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;

class ProductApiTest extends TestCase
{
    private function client(): Client
    {
        return new Client([
            'base_uri' => getenv('APP_URL') ?: 'http://localhost/ArcaneCore_projet/pos_systeme/public/',
            'timeout'  => 2,
            'http_errors' => false,
        ]);
    }

    public function testProduitsEndpointIsReachable(): void
    {
        try {
            $response = $this->client()->get('api/produits');
            $status = $response->getStatusCode();

            $this->assertContains($status, [200, 401, 403], 'Endpoint must be reachable');
            $this->assertStringContainsString('json', $response->getHeaderLine('Content-Type'));
        } catch (ConnectException $e) {
            $this->markTestSkipped('Local server not running: ' . $e->getMessage());
        }
    }
}
