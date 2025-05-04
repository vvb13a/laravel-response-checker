<?php

namespace Vvb13a\LaravelResponseChecker\Concerns;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;

trait ResolvesCrawlerDependency
{
    /**
     * Attempts to resolve/parse the Crawler instance for the given Response.
     * If parsing fails for expected HTML content, it generates an INFO Finding.
     *
     * @param  string  $url  Context URL for potential Finding.
     * @param  Response  $response  The Response object.
     * @param  string  $checkName  The name of the calling check for context.
     * @param  array &$findings  Reference to the findings array to add INFO finding directly.
     * @return Crawler|null Returns Crawler instance on success, null otherwise (parsing failed, not HTML, or other error).
     */
    protected function getCrawlerOrLogSkipFinding(
        string $url,
        Response $response,
        string $checkName,
        array &$findings
    ): ?Crawler {
        $container = app();

        // 1. Check if already resolved/bound
        if ($container->bound(Crawler::class)) {
            try {
                $crawler = resolve(Crawler::class);
                return $crawler instanceof Crawler ? $crawler : null; // Return existing instance if valid
            } catch (BindingResolutionException $e) {
                Log::error("[ResponseChecker] Error resolving already bound Crawler in check: {$checkName}",
                    ['exception' => $e]);
                // Add an ERROR finding directly for this internal issue? Or let check handle null?
                // Let's add an INFO finding indicating resolution failure, as it prevented the check.
                $findings[] = Finding::info("Skipped Check: Internal error resolving Crawler dependency.", $checkName,
                    $url, ['error' => $e->getMessage()]);
                return null; // Cannot proceed
            }
        }

        // 2. Not bound yet, check if response is parseable HTML
        if (!$this->canParseResponse($response)) {
            return null; // Not HTML, normal skip, no finding needed.
        }

        // 3. Attempt to parse HTML and bind
        try {
            $crawler = new Crawler($response->body());
            $container->instance(Crawler::class, $crawler); // Bind as singleton instance
            return $crawler; // Return the newly parsed crawler
        } catch (Throwable $parseError) {
            Log::error("[ResponseChecker] DOM parsing failed during lazy instantiation in check: {$checkName}",
                ['url' => $url, 'exception' => $parseError]);
            // Parsing failed for HTML content, add an INFO finding
            $findings[] = Finding::info(
                message: "Skipped Check: HTML content parsing failed.",
                checkName: $checkName, // Use the passed check name
                url: $url,
                details: ['reason' => 'Could not parse DOM structure.', 'error' => $parseError->getMessage()]
            );
            return null; // Return null as crawler is not available
        }
    }

    /** Check if the response content type appears to be HTML. */
    protected function canParseResponse(Response $response): bool
    {
        $contentType = strtolower($response->header('Content-Type') ?? '');
        return Str::contains($contentType, 'text/html');
    }
}