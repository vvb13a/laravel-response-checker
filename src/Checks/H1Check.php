<?php

namespace Vvb13a\LaravelResponseChecker\Checks;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vvb13a\LaravelResponseChecker\Concerns\ProvidesConfigurationDetails;
use Vvb13a\LaravelResponseChecker\Concerns\SupportsDomChecks;
use Vvb13a\LaravelResponseChecker\Contracts\CheckInterface;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class H1Check implements CheckInterface
{
    use SupportsDomChecks;
    use ProvidesConfigurationDetails;

    // --- Configuration Properties ---
    protected ?int $maxHeadingLength = 70;
    protected ?int $minHeadingLength = 20;
    protected FindingLevel $missingLevel = FindingLevel::ERROR;
    protected FindingLevel $emptyLevel = FindingLevel::ERROR;
    protected FindingLevel $lengthLevel = FindingLevel::WARNING;
    protected FindingLevel $multipleLevel = FindingLevel::WARNING;
    // -----------------------------

    /**
     * Check for the presence, uniqueness, and basic quality of the <h1> tag.
     *
     * @param  string  $url  The absolute URL being checked (for context).
     * @return iterable<Finding> An array of Finding DTOs for heading issues, or empty if valid.
     */
    public function check(string $url, Response $response): iterable
    {
        $checkName = class_basename(static::class);
        $findings = [];
        $configuration = $this->getConfigurationDetails();

        $crawler = $this->tryCreateCrawler($url, $response, self::class);

        if ($crawler === null) {
            $findings[] = Finding::error(
                message: "Skipped Check: Response was not parseable HTML or a parsing error occurred.",
                checkName: self::class,
                url: $url,
                configuration: $configuration
            );
            return $findings;
        }

        try {
            $headingNodes = $crawler->filter('h1');
            $headingNodeCount = $headingNodes->count();

            // 1. Check for Missing Heading
            if ($headingNodeCount === 0) {
                $this->addFinding($findings, 'missing', 'Missing <h1> tag.', $checkName, $url, $configuration);
                return $findings;
            }

            // 2. Check for Multiple Headings
            if ($headingNodeCount > 1) {
                $this->addFinding($findings, 'multiple', 'Multiple <h1> tags found.', $checkName, $url,
                    $configuration,
                    ['count' => $headingNodeCount]);
            }

            // 3. Check Content and Length of the first heading tag
            $headingContent = trim($headingNodes->first()->text());
            if (empty($headingContent)) {
                $this->addFinding($findings, 'empty', '<h1> tag is empty or contains only whitespace.', $checkName,
                    $url, $configuration);
            } else {
                $headingLength = mb_strlen($headingContent);
                if ($this->minHeadingLength !== null && $this->minHeadingLength > 0 && $headingLength < $this->minHeadingLength) {
                    $this->addFinding($findings, 'length',
                        "Heading length ({$headingLength}) is less than minimum ({$this->minHeadingLength}).",
                        $checkName,
                        $url, $configuration,
                        [
                            'heading' => $headingContent,
                            'length' => $headingLength,
                            'limit' => $this->minHeadingLength,
                            'type' => 'min'
                        ]);
                }
                if ($this->maxHeadingLength !== null && $this->maxHeadingLength > 0 && $headingLength > $this->maxHeadingLength) {
                    $this->addFinding($findings, 'length',
                        "Heading length ({$headingLength}) exceeds maximum ({$this->maxHeadingLength}).", $checkName,
                        $url,
                        $configuration,
                        [
                            'heading' => $headingContent,
                            'length' => $headingLength,
                            'limit' => $this->maxHeadingLength,
                            'type' => 'max'
                        ]);
                }
            }

        } catch (Throwable $e) {
            return [Finding::error("Error during heading check: ".$e->getMessage(), $checkName, $url, $configuration)];
        }

        return $findings ?: [$this->getSuccessFinding($url, $checkName, $configuration)];
    }

    /**
     * Helper to add a Finding DTO to the results array based on type and level.
     */
    protected function addFinding(
        array &$findings,
        string $type,
        string $message,
        string $checkName,
        string $url,
        ?array $configuration = null,
        ?array $details = null,
    ): void {
        $level = $this->getLevelForIssueType($type);
        $details['issue_type'] = $type;

        $findings[] = new Finding(
            level: $level,
            message: $message,
            checkName: $checkName,
            url: $url,
            configuration: $configuration,
            details: $details
        );
    }

    /**
     * Get the FindingLevel Enum based on the configured property for a specific issue type.
     */
    protected function getLevelForIssueType(string $type): FindingLevel
    {
        return match ($type) {
            'missing' => $this->missingLevel,
            'empty' => $this->emptyLevel,
            'length' => $this->lengthLevel,
            'multiple' => $this->multipleLevel,
            default => tap(FindingLevel::WARNING, function () use ($type) {
                Log::warning("[ResponseChecker] Unknown issue type '{$type}' encountered in ".class_basename(static::class).". Defaulting level to WARNING.");
            }),
        };
    }

    protected function getSuccessFinding(string $url, string $checkName, array $configuration): Finding
    {
        return Finding::success(
            message: 'Heading is present and has appropriate length.',
            checkName: $checkName,
            url: $url,
            configuration: $configuration,
        );
    }
}