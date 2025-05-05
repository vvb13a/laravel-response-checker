<?php

namespace Vvb13a\LaravelResponseChecker\Concerns;

/**
 * Trait to easily export the configuration properties of a check class.
 */
trait ProvidesConfigurationDetails
{
    /**
     * Returns an array of the check's accessible properties (public/protected)
     * intended to represent its configuration.
     * Excludes null values by default.
     *
     * @param  bool  $filterNulls  Whether to filter out properties with null values.
     * @return array<string, mixed> An associative array of property names and values.
     */
    protected function getConfigurationDetails(bool $filterNulls = true): array
    {
        $config = get_object_vars($this);

        if ($filterNulls) {
            $config = array_filter($config, fn($value) => $value !== null);
        }

        return $config;
    }
}