<?php

namespace Vvb13a\LaravelResponseChecker\Checks;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;
use Vvb13a\LaravelResponseChecker\Concerns\ProvidesConfigurationDetails;
use Vvb13a\LaravelResponseChecker\Concerns\SupportsDomChecks;
use Vvb13a\LaravelResponseChecker\Contracts\CheckInterface;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class ImageAltTextCheck implements CheckInterface
{
    use SupportsDomChecks;
    use ProvidesConfigurationDetails;

    // --- Configuration Properties ---
    protected bool $flagEmptyAlt = true;
    protected FindingLevel $missingAltLevel = FindingLevel::WARNING;
    protected FindingLevel $emptyAltLevel = FindingLevel::WARNING;
    // -----------------------------

    /**
     * Check if <img> tags have appropriate alt attributes (present and optionally non-empty).
     *
     * @param  string  $url  The absolute URL being checked (for context).
     * @return iterable<Finding> An array of Finding DTOs for each image issue found, or empty if all pass.
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
            $images = $crawler->filter('img');

            if ($images->count() === 0) {
                return []; // No images, no findings
            }

            $images->each(function (Crawler $node) use ($url, $checkName, &$findings, $configuration) {
                $src = $node->attr('src') ?? '[Image source missing]';
                $altExists = $node->getNode(0)->hasAttribute('alt');
                $currentIssue = null; // Track issue for *this* image

                // 1. Check for missing alt attribute
                if (!$altExists) {
                    $currentIssue = [
                        'type' => 'missing',
                        'message' => 'Missing alt attribute.',
                        'src' => $src,
                    ];
                } // 2. Check for empty alt attribute (if configured and alt exists)
                elseif ($this->flagEmptyAlt) {
                    $altText = trim($node->attr('alt') ?? '');
                    if ($altText === '') {
                        $currentIssue = [
                            'type' => 'empty',
                            'message' => 'Alt attribute is empty (alt="").',
                            'src' => $src,
                        ];
                    }
                }

                // If an issue was found for this image, create a Finding DTO
                if ($currentIssue) {
                    // Determine level using the helper method based on type
                    $level = $this->getLevelForIssueType($currentIssue['type']);
                    $details = Arr::only($currentIssue, ['type', 'src']);

                    // Use the constructor directly with the determined level
                    $findings[] = new Finding(
                        level: $level,
                        message: $currentIssue['message'],
                        checkName: $checkName,
                        url: $url,
                        configuration: $configuration,
                        details: $details
                    );
                }
            });

        } catch (Throwable $e) {
            return [
                Finding::error(
                    message: 'Error processing images: '.$e->getMessage(),
                    checkName: $checkName,
                    url: $url,
                    configuration: $configuration,
                )
            ];
        }

        return $findings ?: [$this->getSuccessFinding($url, $checkName, $configuration)];
    }

    /**
     * Get the FindingLevel Enum based on the configured property for a specific issue type.
     */
    protected function getLevelForIssueType(string $type): FindingLevel
    {
        return match ($type) {
            'missing' => $this->missingAltLevel,
            'empty' => $this->emptyAltLevel,
            default => tap(FindingLevel::WARNING, function () use ($type) {
                Log::warning("[ResponseChecker] Unknown issue type '{$type}' encountered in ".class_basename(static::class).". Defaulting level to WARNING.");
            }),
        };
    }

    protected function getSuccessFinding(string $url, string $checkName, array $configuration): Finding
    {
        return Finding::success(
            message: 'All images have appropriate alt attributes.',
            checkName: $checkName,
            url: $url,
            configuration: $configuration,
        );
    }
}