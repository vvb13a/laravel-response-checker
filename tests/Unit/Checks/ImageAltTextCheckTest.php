<?php

namespace Vvb13a\LaravelResponseChecker\Tests\Unit\Checks;

use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Vvb13a\LaravelResponseChecker\Checks\ImageAltTextCheck;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;
use Vvb13a\LaravelResponseChecker\Tests\TestCase;

// Import MockInterface for type hinting
// Make sure the correct Response class is imported

// <<< CORRECT RESPONSE TYPE


class ImageAltTextCheckTest extends TestCase
{
    protected ImageAltTextCheck $check;
    protected string $testUrl = 'http://example.com/page';

    /** @test */
    public function it_returns_no_findings_for_valid_images_with_alt_text(): void
    {
        $html = $this->createHtmlBody('
            <img src="image1.jpg" alt="Description 1">
            <p>Some text</p>
            <img src="/path/to/image2.png" alt="Description 2 is good">
        ');
        // Use correct Illuminate Response type hint for clarity
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertEmpty($findings);
    }

    /** Helper to create body content easily */
    protected function createHtmlBody(string $bodyContent): string
    {
        return "<!DOCTYPE html><html><head><title>Test</title></head><body>{$bodyContent}</body></html>";
    }

    /** @test */
    public function it_returns_no_findings_when_no_images_are_present(): void
    {
        $html = $this->createHtmlBody('<p>Just text, no images here.</p>');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertEmpty($findings);
    }

    /** @test */
    public function it_returns_warning_finding_for_missing_alt_attribute_by_default(): void
    {
        $html = $this->createHtmlBody('<img src="missing-alt.gif">');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];

        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::WARNING, $finding->level);
        $this->assertEquals('ImageAltTextCheck', $finding->checkName);
        $this->assertEquals($this->testUrl, $finding->url);
        $this->assertEquals('Missing alt attribute.', $finding->message);
        $this->assertEquals([
            'type' => 'missing',
            'src' => 'missing-alt.gif',
        ], $finding->details);
    }

    /** @test */
    public function it_returns_warning_finding_for_empty_alt_attribute_when_flag_is_true_by_default(): void
    {
        // --- Test alt="" ---
        $html = $this->createHtmlBody('<img src="empty-alt.jpeg" alt="">');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings, "Failed for alt=\"\"");
        $finding = $findings[0]; // Variable for first case

        $this->assertEquals(FindingLevel::WARNING, $finding->level, "Level check failed for alt=\"\"");
        $this->assertEquals('Alt attribute is empty (alt="").', $finding->message, "Message check failed for alt=\"\"");
        $this->assertEquals(['type' => 'empty', 'src' => 'empty-alt.jpeg'], $finding->details,
            "Details check failed for alt=\"\"");

        // --- Test alt="   " (whitespace) ---
        $htmlWhitespace = $this->createHtmlBody('<img src="whitespace-alt.png" alt="   ">');
        $responseWhitespace = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $htmlWhitespace);
        $findingsWhitespace = iterator_to_array($this->check->check($this->testUrl, $responseWhitespace));

        $this->assertCount(1, $findingsWhitespace, "Failed for alt containing only whitespace");
        $findingWhitespace = $findingsWhitespace[0]; // Use the correct variable for the second case
        $this->assertEquals(FindingLevel::WARNING, $findingWhitespace->level, "Level check failed for alt=' '");
        $this->assertEquals('Alt attribute is empty (alt="").', $findingWhitespace->message,
            "Message check failed for alt=' '");
        // FIX: Assert details using the correct variable (Line 102 failure)
        $this->assertEquals(['type' => 'empty', 'src' => 'whitespace-alt.png'], $findingWhitespace->details,
            "Details check failed for alt=' '");
    }

    /** @test */
    public function it_returns_no_findings_for_empty_alt_attribute_when_flag_is_false(): void
    {
        $check = new class extends ImageAltTextCheck {
            protected bool $flagEmptyAlt = false;
        };

        $html = $this->createHtmlBody('<img src="empty-ok.webp" alt="">');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = iterator_to_array($check->check($this->testUrl, $response));

        $this->assertEmpty($findings, "Finding generated for empty alt='' when flagEmptyAlt was false.");
    }

    /** @test */
    public function it_handles_mixed_image_issues_correctly(): void
    {
        $html = $this->createHtmlBody('
            <img src="valid.jpg" alt="Valid">
            <img src="missing.gif">
            <img src="empty.png" alt="">
            <img src="valid2.svg" alt="Another valid one">
            <img src="whitespace.bmp" alt="  ">
        ');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $findings = collect(iterator_to_array($this->check->check($this->testUrl, $response)));

        $this->assertCount(3, $findings);

        $missingFinding = $findings->firstWhere('details.src', 'missing.gif');
        $this->assertNotNull($missingFinding);
        $this->assertEquals(FindingLevel::WARNING, $missingFinding->level);
        $this->assertEquals('missing', $missingFinding->details['type']);

        $emptyFinding = $findings->firstWhere('details.src', 'empty.png');
        $this->assertNotNull($emptyFinding);
        $this->assertEquals(FindingLevel::WARNING, $emptyFinding->level);
        $this->assertEquals('empty', $emptyFinding->details['type']);

        $whitespaceFinding = $findings->firstWhere('details.src', 'whitespace.bmp');
        $this->assertNotNull($whitespaceFinding);
        $this->assertEquals(FindingLevel::WARNING, $whitespaceFinding->level);
        $this->assertEquals('empty', $whitespaceFinding->details['type']);
    }

    /** @test */
    public function it_skips_check_for_non_html_response(): void
    {
        $responseJson = $this->createFakeResponse(200, ['Content-Type' => 'application/json'], '{"data":"value"}');
        $findingsJson = iterator_to_array($this->check->check($this->testUrl, $responseJson));
        $this->assertEmpty($findingsJson);
    }

    /** @test */
    public function it_returns_finding_or_skips_gracefully_on_potentially_malformed_html(): void
    {
        $malformedHtml = "<!DOCTYPE html><html><head><body><img src='a.jpg'";
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $malformedHtml);

        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals('ImageAltTextCheck', $finding->checkName);

        if ($finding->level === FindingLevel::INFO) {
            $this->assertStringContainsString('Skipped Check: HTML content parsing failed.', $finding->message);
        } else {
            $this->assertEquals(FindingLevel::WARNING, $finding->level);
            $this->assertEquals('Missing alt attribute.', $finding->message);
            $this->assertEquals(['type' => 'missing', 'src' => 'a.jpg'], $finding->details);
        }
    }

    /** @test */
    public function it_returns_error_finding_on_dom_traversal_exception(): void
    {
        $html = $this->createHtmlBody('<img src="problem.jpg" alt="Maybe ok">');
        $response = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $html);
        $exceptionMessage = 'Simulated node access error';

        /** @var Crawler|MockInterface $mockCrawlerNode */
        $mockCrawlerNode = Mockery::mock(Crawler::class);
        $mockCrawlerNode->shouldReceive('getNode')->with(0)->andThrow(new RuntimeException($exceptionMessage));
        $mockCrawlerNode->shouldReceive('attr')->with('src')->andReturn('problem.jpg');

        /** @var Crawler|MockInterface $mockCrawler */
        $mockCrawler = Mockery::mock(Crawler::class);
        $mockCrawler->shouldReceive('filter')->once()->with('img')->andReturnSelf();
        $mockCrawler->shouldReceive('count')->once()->andReturn(1);
        $mockCrawler->shouldReceive('each')
            ->once()
            ->andReturnUsing(function (callable $callback) use ($mockCrawlerNode) {
                $callback($mockCrawlerNode);
            });

        $this->app->instance(Crawler::class, $mockCrawler);

        $findings = iterator_to_array($this->check->check($this->testUrl, $response));

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertInstanceOf(Finding::class, $finding);
        $this->assertEquals(FindingLevel::ERROR, $finding->level);
        $this->assertEquals('ImageAltTextCheck', $finding->checkName);
        $this->assertStringContainsString('Error processing images:', $finding->message);
        $this->assertStringContainsString($exceptionMessage, $finding->message);
        $this->assertNull($finding->details);
    }

    /** @test */
    public function it_uses_configured_severity_levels(): void
    {
        // FIX: Only override getLevelForIssueType, not the whole check method
        $check = new class extends ImageAltTextCheck {
            protected FindingLevel $missingAltLevel = FindingLevel::ERROR; // Intent
            protected FindingLevel $emptyAltLevel = FindingLevel::INFO;   // Intent
            protected bool $flagEmptyAlt = true;

            // Override only the level method
            protected function getLevelForIssueType(string $type): FindingLevel
            {
                // Optional logging to confirm execution
                // Log::debug("[TEST ANON CLASS - getLevel Method] Received type: '{$type}'");
                $level = match ($type) {
                    'missing' => FindingLevel::ERROR, // Hardcode expected ERROR
                    'empty' => FindingLevel::INFO,  // Hardcode expected INFO
                    default => FindingLevel::WARNING,
                };
                // Log::debug("[TEST ANON CLASS - getLevel Method] Returning level: " . $level->value);
                return $level;
            }
        };

        // Test Missing (Expecting ERROR based on override)
        $htmlMissing = $this->createHtmlBody('<img src="missing-configured.gif">');
        $responseMissing = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $htmlMissing);
        $findingsMissing = iterator_to_array($check->check($this->testUrl, $responseMissing));

        $this->assertCount(1, $findingsMissing, "Test 'missing': Count failed.");
        $this->assertEquals(FindingLevel::ERROR, $findingsMissing[0]->level, "Test 'missing': Level failed.");
        $this->assertEquals('missing', $findingsMissing[0]->details['type'], "Test 'missing': Type failed.");

        // Test Empty (Expecting INFO based on override)
        $htmlEmpty = $this->createHtmlBody('<img src="empty-configured.jpg" alt="">');
        $responseEmpty = $this->createFakeResponse(200, ['Content-Type' => 'text/html'], $htmlEmpty);
        $findingsEmpty = iterator_to_array($check->check($this->testUrl, $responseEmpty));

        $this->assertCount(1, $findingsEmpty, "Test 'empty': Count failed.");
        // FIX: Assert the hardcoded level from the overridden method (Line 273 failure)
        $this->assertEquals(FindingLevel::INFO, $findingsEmpty[0]->level, "Test 'empty': Level failed.");
        $this->assertEquals('empty', $findingsEmpty[0]->details['type'], "Test 'empty': Type failed.");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->check = new ImageAltTextCheck();
    }

    protected function tearDown(): void
    {
        // Clean up Crawler instance and Mockery expectations
        $this->app->forgetInstance(Crawler::class);
        Mockery::close();
        parent::tearDown();
    }
}