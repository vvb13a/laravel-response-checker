<?php

namespace Vvb13a\LaravelResponseChecker\Tests\Unit\Checks;

use Symfony\Component\DomCrawler\Crawler;
use Vvb13a\LaravelResponseChecker\Checks\TitleCheck;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;
use Vvb13a\LaravelResponseChecker\Tests\TestCase;

class TitleCheckTest extends TestCase
{
    protected TitleCheck $check;
    protected string $testUrl = 'http://example.com/test';

    /** @test */
    public function it_returns_no_findings_for_a_valid_title(): void
    {
        $html = $this->createHtml('<title>This is a Perfectly Valid Title</title>');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertEmpty($findings);
    }

    /**
     * Helper to create HTML structure easily.
     */
    protected function createHtml(string $headContent = '', string $bodyContent = ''): string
    {
        return "<!DOCTYPE html><html><head>{$headContent}</head><body>{$bodyContent}</body></html>";
    }

    /** @test */
    public function it_returns_an_error_finding_if_title_tag_is_missing(): void
    {
        $html = $this->createHtml('<meta charset="utf-8">', '<h1>No Title</h1>'); // No <title> tag
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::ERROR, $finding->level); // Default missing level
        $this->assertEquals('TitleCheck', $finding->checkName);
        $this->assertEquals($this->testUrl, $finding->url);
        $this->assertEquals('Missing <title> tag.', $finding->message);
        $this->assertEquals(['issue_type' => 'missing'], $finding->details);
    }

    /** @test */
    public function it_returns_a_warning_finding_if_multiple_title_tags_exist(): void
    {
        $html = $this->createHtml('<title>First Title</title><title>Second Title</title>');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings); // Only the 'multiple' finding should be reported here if first is valid
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::WARNING, $finding->level); // Default multiple level
        $this->assertEquals('TitleCheck', $finding->checkName);
        $this->assertEquals($this->testUrl, $finding->url);
        $this->assertEquals('Multiple <title> tags found.', $finding->message);
        $this->assertEquals(['issue_type' => 'multiple', 'count' => 2], $finding->details);
    }

    /** @test */
    public function it_returns_multiple_findings_if_multiple_titles_and_first_is_invalid(): void
    {
        // First title is too short, and there's a second one
        $html = $this->createHtml('<title>Short</title><title>Second Title</title>');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(2, $findings);

        // Check for multiple finding
        $multipleFinding = collect($findings)->firstWhere('details.issue_type', 'multiple');
        $this->assertNotNull($multipleFinding);
        $this->assertEquals(FindingLevel::WARNING, $multipleFinding->level);
        $this->assertEquals('Multiple <title> tags found.', $multipleFinding->message);
        $this->assertEquals(2, $multipleFinding->details['count']);

        // Check for length finding
        $lengthFinding = collect($findings)->firstWhere('details.issue_type', 'length');
        $this->assertNotNull($lengthFinding);
        $this->assertEquals(FindingLevel::WARNING, $lengthFinding->level);
        $this->assertStringContainsString('Title length (5) is less than minimum (10).', $lengthFinding->message);
        $this->assertEquals('min', $lengthFinding->details['type']);
    }

    /** @test */
    public function it_returns_an_error_finding_if_title_tag_is_empty(): void
    {
        $html = $this->createHtml('<title></title>');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::ERROR, $finding->level); // Default empty level
        $this->assertEquals('TitleCheck', $finding->checkName);
        $this->assertEquals('<title> tag is empty or contains only whitespace.', $finding->message);
        $this->assertEquals(['issue_type' => 'empty'], $finding->details);
    }

    /** @test */
    public function it_returns_an_error_finding_if_title_tag_contains_only_whitespace(): void
    {
        $html = $this->createHtml('<title>   </title>'); // Whitespace only
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::ERROR, $finding->level); // Default empty level
        $this->assertEquals('<title> tag is empty or contains only whitespace.', $finding->message);
        $this->assertEquals(['issue_type' => 'empty'], $finding->details);
    }

    /** @test */
    public function it_returns_a_warning_finding_if_title_is_too_short(): void
    {
        $html = $this->createHtml('<title>Too short</title>'); // 9 chars, default min is 10
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::WARNING, $finding->level); // Default length level
        $this->assertEquals('TitleCheck', $finding->checkName);
        $this->assertStringContainsString('Title length (9) is less than minimum (10).', $finding->message);
        $this->assertEquals([
            'issue_type' => 'length',
            'length' => 9,
            'limit' => 10, // Default min length
            'type' => 'min',
        ], $finding->details);
    }

    /** @test */
    public function it_returns_a_warning_finding_if_title_is_too_long(): void
    {
        // Default max is 60
        $longTitle = str_repeat('a', 61);
        $html = $this->createHtml("<title>{$longTitle}</title>");
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::WARNING, $finding->level); // Default length level
        $this->assertEquals('TitleCheck', $finding->checkName);
        $this->assertStringContainsString('Title length (61) exceeds maximum (60).', $finding->message);
        $this->assertEquals([
            'issue_type' => 'length',
            'length' => 61,
            'limit' => 60, // Default max length
            'type' => 'max',
        ], $finding->details);
    }

    /** @test */
    public function it_returns_no_findings_if_length_limits_are_null(): void
    {
        // Use anonymous class to override protected properties for testing
        $check = new class extends TitleCheck {
            protected ?int $maxTitleLength = null;
            protected ?int $minTitleLength = null;
        };

        $shortTitle = 'Short'; // Should pass now
        $longTitle = str_repeat('a', 100); // Should pass now

        $htmlShort = $this->createHtml("<title>{$shortTitle}</title>");
        $responseShort = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $htmlShort);
        $findingsShort = iterator_to_array($check->check($this->testUrl, $responseShort));
        $this->assertEmpty($findingsShort, "Should pass with short title when minLength is null");

        $htmlLong = $this->createHtml("<title>{$longTitle}</title>");
        $responseLong = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $htmlLong);
        $findingsLong = iterator_to_array($check->check($this->testUrl, $responseLong));
        $this->assertEmpty($findingsLong, "Should pass with long title when maxLength is null");
    }

    /** @test */
    public function it_skips_check_and_returns_no_findings_for_non_html_response(): void
    {
        // No Content-Type header or non-HTML type
        $responseJson = $this->createFakeResponse(200, ['Content-Type' => 'application/json'], '{"data":"value"}');
        $responseNoHeader = $this->createFakeResponse(200, [], 'Some text');

        $findingsJson = iterator_to_array($this->check->check($this->testUrl, $responseJson));
        $findingsNoHeader = iterator_to_array($this->check->check($this->testUrl, $responseNoHeader));

        $this->assertEmpty($findingsJson, "Check should be skipped for JSON content type");
        $this->assertEmpty($findingsNoHeader, "Check should be skipped for missing content type");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->check = new TitleCheck();
    }

    protected function tearDown(): void
    {
        $this->app->forgetInstance(Crawler::class);
        parent::tearDown();
    }
}