<?php

namespace Vvb13a\LaravelResponseChecker\Contracts;

use Illuminate\Http\Client\Response;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;

interface CheckInterface
{
    /**
     * Perform checks on the given response.
     * Checks needing the DOM should resolve the Crawler internally.
     *
     * @param  string  $url  The absolute URL being checked (for context).
     * @param  Response  $response  The Illuminate HTTP Client Response object.
     * @return iterable<Finding> An iterable of Finding DTOs. Empty if no findings to report.
     */
    // Only explicit Response needed
    public function check(string $url, Response $response): iterable;
}