<?php

namespace Vvb13a\LaravelResponseChecker\Exceptions;

use RuntimeException;
use Throwable;
use Vvb13a\LaravelResponseChecker\DTOs\Finding;

/**
 * Thrown when a CheckInterface implementation returns an invalid result
 * (e.g., not an iterable or contains items other than FindingDTO).
 */
class InvalidReturnedCheckFindingException extends RuntimeException
{
    /**
     * The class name of the check that returned the invalid result.
     * @var string
     */
    public string $checkClassName;

    /**
     * The type of the invalid data returned.
     * @var string
     */
    public string $returnedDataType;

    /**
     * Constructor.
     *
     * @param  string  $checkClassName  The FQCN of the faulty check.
     * @param  mixed  $invalidData  The actual invalid data returned.
     * @param  string  $message  Optional custom message.
     * @param  int  $code  Optional error code.
     * @param  Throwable|null  $previous  Optional previous exception.
     */
    public function __construct(
        string $checkClassName,
        mixed $invalidData,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->checkClassName = $checkClassName;
        $this->returnedDataType = get_debug_type($invalidData); // Get more detailed type info

        if (empty($message)) {
            $message = sprintf(
                "Check '%s' returned an invalid result type '%s'. Expected an iterable containing only %s instances.",
                $this->checkClassName,
                $this->returnedDataType,
                Finding::class // Use Finding::class directly
            );
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the class name of the check that caused the error.
     */
    public function getCheckClassName(): string
    {
        return $this->checkClassName;
    }

    /**
     * Get the type of the data that was incorrectly returned.
     */
    public function getReturnedDataType(): string
    {
        return $this->returnedDataType;
    }
}