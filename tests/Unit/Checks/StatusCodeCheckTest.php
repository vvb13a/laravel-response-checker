<?php

namespace Vvb13a\LaravelResponseChecker\Tests\Unit\Checks;

use Vvb13a\LaravelResponseChecker\Checks\StatusCodeCheck;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;
use Vvb13a\LaravelResponseChecker\Tests\TestCase;

class StatusCodeCheckTest extends TestCase
{
    protected StatusCodeCheck $check;
    protected string $testUrl = 'http://example.com/test';

    /** @test */
    public function it_returns_no_findings_for_successful_2xx_status_codes(): void
    {
        $response = $this->createFakeResponse(200);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertEmpty($findings);

        $response = $this->createFakeResponse(204); // No content
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertEmpty($findings);
    }

    /** @test */
    public function it_returns_a_warning_finding_for_redirect_3xx_status_codes_by_default(): void
    {
        $redirectLocation = 'http://example.com/redirected';
        $response = $this->createFakeResponse(302, ['Location' => $redirectLocation]); // Found (Temporary Redirect)
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::WARNING, $finding->level);
        $this->assertEquals('StatusCodeCheck', $finding->checkName);
        $this->assertEquals($this->testUrl, $finding->url);
        $this->assertStringContainsString('Page redirected (302)', $finding->message);
        $this->assertEquals([
            'status_code' => 302,
            'redirect_location' => $redirectLocation,
        ], $finding->details);

        // Test another redirect code
        $response = $this->createFakeResponse(301, ['Location' => $redirectLocation]); // Moved Permanently
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertEquals(FindingLevel::WARNING, $finding->level);
        $this->assertStringContainsString('Page redirected (301)', $finding->message);
        $this->assertEquals(301, $finding->details['status_code']);
    }

    /** @test */
    public function it_returns_an_error_finding_for_client_error_4xx_status_codes_by_default(): void
    {
        $response = $this->createFakeResponse(404); // Not Found
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::ERROR, $finding->level);
        $this->assertEquals('StatusCodeCheck', $finding->checkName);
        $this->assertEquals($this->testUrl, $finding->url);
        $this->assertStringContainsString('Client error response (404)', $finding->message);
        $this->assertEquals(['status_code' => 404], $finding->details);

        // Test another client error code
        $response = $this->createFakeResponse(403); // Forbidden
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertEquals(FindingLevel::ERROR, $finding->level);
        $this->assertStringContainsString('Client error response (403)', $finding->message);
        $this->assertEquals(403, $finding->details['status_code']);
    }

    /** @test */
    public function it_returns_an_error_finding_for_server_error_5xx_status_codes_by_default(): void
    {
        $response = $this->createFakeResponse(500); // Internal Server Error
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::ERROR, $finding->level);
        $this->assertEquals('StatusCodeCheck', $finding->checkName);
        $this->assertEquals($this->testUrl, $finding->url);
        $this->assertStringContainsString('Server error response (500)', $finding->message);
        $this->assertEquals(['status_code' => 500], $finding->details);

        // Test another server error code
        $response = $this->createFakeResponse(503); // Service Unavailable
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertEquals(FindingLevel::ERROR, $finding->level);
        $this->assertStringContainsString('Server error response (503)', $finding->message);
        $this->assertEquals(503, $finding->details['status_code']);
    }

    /** @test */
    public function it_returns_an_error_finding_for_unexpected_status_codes_by_default(): void
    {
        // Test a 1xx code (though less common for direct page responses)
        $response = $this->createFakeResponse(101); // Switching Protocols
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::ERROR, $finding->level); // Default unexpected severity
        $this->assertEquals('StatusCodeCheck', $finding->checkName);
        $this->assertEquals($this->testUrl, $finding->url);
        $this->assertStringContainsString('Received unexpected status code: 101', $finding->message);
        $this->assertEquals(['status_code' => 101], $finding->details);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->check = new StatusCodeCheck();
    }
}