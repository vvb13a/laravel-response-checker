<?php

namespace Vvb13a\LaravelResponseChecker\Checks;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vvb13a\LaravelResponseChecker\Concerns\SupportsDomChecks;
use Vvb13a\LaravelResponseChecker\Contracts\CheckInterface;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class MetaDescriptionCheck implements CheckInterface
{
    use SupportsDomChecks;

    protected ?int $minDescriptionLength = 50;
    protected ?int $maxDescriptionLength = 160;

    protected FindingLevel $missingLevel = FindingLevel::ERROR;
    protected FindingLevel $emptyLevel = FindingLevel::ERROR;
    protected FindingLevel $lengthLevel = FindingLevel::WARNING;
    protected FindingLevel $multipleLevel = FindingLevel::WARNING;

    /**
     * Check for the presence, uniqueness, and basic quality of the <meta name="description"> tag.
     *
     * @param  string  $url  The absolute URL being checked (for context).
     * @return iterable<Finding> An array of Finding DTOs for description issues, or empty if valid.
     */
    public function check(string $url, Response $response): iterable
    {
        $checkName = class_basename(static::class);
        $findings = [];

        $crawler = $this->tryCreateCrawler($url, $response, self::class);

        if ($crawler === null) {
            $findings[] = Finding::error(
                message: "Skipped Check: Response was not parseable HTML or a parsing error occurred.",
                checkName: self::class,
                url: $url
            );
            return $findings;
        }

        try {
            $descNodes = $crawler->filter('head > meta[name="description"]');
            $descNodeCount = $descNodes->count();

            // 1. Check for Missing Description
            if ($descNodeCount === 0) {
                $this->addFinding($findings, 'missing', 'Missing <meta name="description"> tag.', $checkName, $url);
                // If missing, no other checks apply, return early
                return $findings;
            }

            // 2. Check for Multiple Descriptions
            if ($descNodeCount > 1) {
                $this->addFinding($findings, 'multiple', 'Multiple <meta name="description"> tags found.', $checkName,
                    $url, ['count' => $descNodeCount]);
                // Continue to check the content/length of the *first* one found
            }

            // 3. Check Content and Length of the first description tag
            $descriptionContent = trim($descNodes->first()->attr('content') ?? '');
            if (empty($descriptionContent)) {
                $this->addFinding($findings, 'empty', '<meta name="description"> tag content is empty.', $checkName,
                    $url);
            } else {
                // Check Length only if content is not empty
                $descLength = mb_strlen($descriptionContent);
                if ($this->minDescriptionLength !== null && $this->minDescriptionLength > 0 && $descLength < $this->minDescriptionLength) {
                    $this->addFinding($findings, 'length',
                        "Description length ({$descLength}) is less than minimum ({$this->minDescriptionLength}).",
                        $checkName, $url,
                        ['length' => $descLength, 'limit' => $this->minDescriptionLength, 'type' => 'min']);
                }
                if ($this->maxDescriptionLength !== null && $this->maxDescriptionLength > 0 && $descLength > $this->maxDescriptionLength) {
                    $this->addFinding($findings, 'length',
                        "Description length ({$descLength}) exceeds maximum ({$this->maxDescriptionLength}).",
                        $checkName, $url,
                        ['length' => $descLength, 'limit' => $this->maxDescriptionLength, 'type' => 'max']);
                }
            }

        } catch (Throwable $e) {
            // Catch errors during DOM traversal/filtering
            return [Finding::error("Error during meta description check: ".$e->getMessage(), $checkName, $url)];
        }

        return $findings ?: [$this->getSuccessFinding($url, $checkName)];
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
        ?array $details = null
    ): void {
        $level = $this->getLevelForIssueType($type);
        $details['issue_type'] = $type;

        $findings[] = new Finding(
            level: $level,
            message: $message,
            checkName: $checkName,
            url: $url,
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

    protected function getSuccessFinding(string $url, string $checkName): Finding
    {
        return Finding::success(
            message: 'Description is present and has appropriate length.',
            checkName: $checkName,
            url: $url,
        );
    }
}