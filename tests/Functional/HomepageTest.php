<?php

namespace Tests\Functional;

class HomepageTest extends BaseTestCase
{
    /**
     * Test that the ping route returns a 200
     */
    public function testGetPing()
    {
        $response = $this->runApp('GET', '/ping');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('pong', (string)$response->getBody());
    }

    /**
     * Test that the index route with optional name argument returns a rendered greeting
     */
    public function testGetHomepageWithGreeting()
    {
        $response = $this->runApp('GET', '/test/name');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Hello', (string)$response->getBody());
    }

}