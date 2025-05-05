<?php

namespace Vvb13a\LaravelResponseChecker\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

trait SupportsDomChecks
{
    /**
     * Attempts to create a new Crawler instance for the given Response if it's HTML.
     * This method creates a new Crawler instance on every call.
     * It handles potential parsing errors internally and logs them.
     *
     * @param  string  $url  Context URL for logging potential errors.
     * @param  Response  $response  The Response object.
     * @param  string  $checkName  The name of the calling check for logging context.
     * @return Crawler|null Returns a new Crawler instance on success, null if the response
     *                      is not HTML or if DOM parsing fails.
     */
    protected function tryCreateCrawler(
        string $url,
        Response $response,
        string $checkName // Keep for logging context
    ): ?Crawler
    {

        // 1. Check if response appears to be HTML
        if (!$this->canParseResponse($response)) {
            return null; // Not HTML, normal skip.
        }

        // 2. Check if response body is empty
        $body = $response->body();
        if (empty($body)) {
            return null;
        }

        // 3. Attempt to parse HTML and create a new Crawler instance
        try {
            $crawler = new Crawler($body);
            return $crawler;
        } catch (Throwable $parseError) {
            Log::warning("[ResponseChecker] DOM parsing failed for check '{$checkName}'. Check will likely be skipped.",
                [
                    'url' => $url,
                    'exception_message' => $parseError->getMessage(),
                    'exception_class' => get_class($parseError),
                ]);
            return null;
        }
    }

    /**
     * Check if the response content type appears to be HTML.
     * Made public or protected static if useful outside instance context,
     * but protected instance method is fine here.
     */
    protected function canParseResponse(Response $response): bool
    {
        // Ensure header retrieval is case-insensitive and handles potential nulls
        $contentType = $response->header('Content-Type');
        return $contentType && Str::contains(strtolower($contentType), 'text/html');
    }
}