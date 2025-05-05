<?php

namespace Vvb13a\LaravelResponseChecker\Checks;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vvb13a\LaravelResponseChecker\Concerns\SupportsDomChecks;
use Vvb13a\LaravelResponseChecker\Contracts\CheckInterface;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class TitleCheck implements CheckInterface
{
    use SupportsDomChecks;

    protected ?int $maxTitleLength = 60;
    protected ?int $minTitleLength = 10;

    protected FindingLevel $missingLevel = FindingLevel::ERROR;
    protected FindingLevel $emptyLevel = FindingLevel::ERROR;
    protected FindingLevel $lengthLevel = FindingLevel::WARNING;
    protected FindingLevel $multipleLevel = FindingLevel::WARNING;

    /**
     * Check for the presence, uniqueness, and basic quality of the <title> tag.
     *
     * @param  string  $url  The absolute URL being checked (for context).
     * @return iterable<Finding> An array of Finding DTOs for title issues, or empty if valid.
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
            $titleNodes = $crawler->filter('head > title');
            $titleNodeCount = $titleNodes->count();

            // 1. Check for Missing Title
            if ($titleNodeCount === 0) {
                $this->addFinding($findings, 'missing', 'Missing <title> tag.', $checkName, $url);
                // If missing, no other checks apply, return early
                return $findings;
            }

            // 2. Check for Multiple Titles
            if ($titleNodeCount > 1) {
                $this->addFinding($findings, 'multiple', 'Multiple <title> tags found.', $checkName, $url,
                    ['count' => $titleNodeCount]);
                // Continue to check the content/length of the *first* one found
            }

            // 3. Check Content and Length of the first title tag
            $titleContent = trim($titleNodes->first()->text());
            if (empty($titleContent)) {
                $this->addFinding($findings, 'empty', '<title> tag is empty or contains only whitespace.', $checkName,
                    $url);
            } else {
                // Check Length only if content is not empty
                $titleLength = mb_strlen($titleContent);
                if ($this->minTitleLength !== null && $this->minTitleLength > 0 && $titleLength < $this->minTitleLength) {
                    $this->addFinding($findings, 'length',
                        "Title length ({$titleLength}) is less than minimum ({$this->minTitleLength}).", $checkName,
                        $url, ['length' => $titleLength, 'limit' => $this->minTitleLength, 'type' => 'min']);
                }
                if ($this->maxTitleLength !== null && $this->maxTitleLength > 0 && $titleLength > $this->maxTitleLength) {
                    $this->addFinding($findings, 'length',
                        "Title length ({$titleLength}) exceeds maximum ({$this->maxTitleLength}).", $checkName, $url,
                        ['length' => $titleLength, 'limit' => $this->maxTitleLength, 'type' => 'max']);
                }
            }

        } catch (Throwable $e) {
            // Catch errors during DOM traversal/filtering
            return [Finding::error("Error during title check: ".$e->getMessage(), $checkName, $url)];
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
            message: 'Title is present and has appropriate length.',
            checkName: $checkName,
            url: $url,
        );
    }
}