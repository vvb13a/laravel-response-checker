<?php

namespace Vvb13a\LaravelResponseChecker\Tests;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Response;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Define environment setup.
     * @param  Application  $app
     * @return void
     */
    public function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:yourRandomTestAppKeyGeneratethisOnce='); // Use same test key
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * No package providers needed for this core package typically.
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            // No Service Provider for this package currently
        ];
    }

    /** Helper to create fake Illuminate Response */
    protected function createFakeResponse(int $status = 200, array $headers = [], ?string $body = ''): Response
    {
        $psrResponse = new Psr7Response($status, $headers, $body);
        return new Response($psrResponse);
    }
}