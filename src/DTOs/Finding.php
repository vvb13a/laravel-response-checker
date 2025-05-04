<?php

namespace Vvb13a\LaravelResponseChecker\DTOs;

use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

/**
 * Data Transfer Object representing the outcome of a single check execution.
 */
readonly class Finding // Using readonly properties (PHP 8.1+)
{
    public function __construct(
        public FindingLevel $level,
        public string $message,
        public string $checkName,
        public string $url,
        public ?array $details = null
    ) {
    }

    public static function success(string $message, string $checkName, string $url, ?array $details = null): self
    {
        return new self(FindingLevel::SUCCESS, $message, $checkName, $url, $details);
    }

    public static function info(string $message, string $checkName, string $url, ?array $details = null): self
    {
        return new self(FindingLevel::INFO, $message, $checkName, $url, $details);
    }

    public static function warning(string $message, string $checkName, string $url, ?array $details = null): self
    {
        return new self(FindingLevel::WARNING, $message, $checkName, $url, $details);
    }

    public static function error(string $message, string $checkName, string $url, ?array $details = null): self
    {
        return new self(FindingLevel::ERROR, $message, $checkName, $url, $details);
    }
}