<?php

namespace Vvb13a\LaravelResponseChecker\Checks;

use Illuminate\Http\Client\Response;
use Vvb13a\LaravelResponseChecker\Contracts\CheckInterface;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class StatusCodeCheck implements CheckInterface
{
    protected FindingLevel $redirectLevel = FindingLevel::WARNING;
    protected FindingLevel $clientErrorSeverity = FindingLevel::ERROR;
    protected FindingLevel $serverErrorSeverity = FindingLevel::ERROR;
    protected FindingLevel $unexpectedErrorSeverity = FindingLevel::ERROR;

    /**
     * Check the HTTP status code of the response.
     *
     * @param  string  $url  The absolute URL being checked (for context).
     * @return iterable<Finding> An array containing Finding DTOs for issues found, or empty if status is acceptable.
     */
    public function check(string $url, Response $response): iterable // Updated signature
    {
        $checkName = class_basename(static::class);
        $statusCode = $response->status();

        // 1. Successful (2xx) - Standard case, return no findings
        if ($response->successful()) {
            return [$this->getSuccessFinding($url, $checkName, $statusCode)];
        }

        // 2. Redirect (3xx)
        if ($response->redirect()) {
            // Check the configured level for redirects
            if ($this->redirectLevel === FindingLevel::SUCCESS) {
                return [$this->getSuccessFinding($url, $checkName, $statusCode)];
            }

            // If not success, create a finding with the configured level
            $targetLocation = $response->header('Location');
            $details = [
                'status_code' => $statusCode,
                'redirect_location' => $targetLocation,
            ];
            $message = "Page redirected ({$statusCode})";

            return [new Finding($this->redirectLevel, $message, $checkName, $url, $details)];
        }

        // 3. Client Errors (4xx)
        if ($response->clientError()) {
            $message = "Client error response ({$statusCode})";
            $details = ['status_code' => $statusCode];

            return [new Finding($this->clientErrorSeverity, $message, $checkName, $url, $details)];
        }

        // 4. Server Errors (5xx)
        if ($response->serverError()) {
            $message = "Server error response ({$statusCode})";
            $details = ['status_code' => $statusCode];

            return [new Finding($this->serverErrorSeverity, $message, $checkName, $url, $details)];
        }

        // 5. Other non-2xx/3xx/4xx/5xx codes (Uncommon)
        $message = "Received unexpected status code: {$statusCode}";
        $details = ['status_code' => $statusCode];

        return [new Finding($this->unexpectedErrorSeverity, $message, $checkName, $url, $details)];
    }

    protected function getSuccessFinding(string $url, string $checkName, int $statusCode): Finding
    {
        return Finding::success(
            message: 'Status code indicates success.',
            checkName: $checkName,
            url: $url,
            details: ['status_code' => $statusCode]
        );
    }
}