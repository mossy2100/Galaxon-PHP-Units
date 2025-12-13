<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;
use ValueError;

class Temperature extends Measurement
{
    // region Factory methods

    /**
     * Parse a temperature string.
     *
     * Accepts standard formats like "25C", "98.6F", "273.15K"
     * or with degree symbols like "25°C", "98.6°F".
     *
     * @param string $value The string to parse.
     * @return static A new Temperature instance.
     * @throws ValueError If the string is not a valid temperature format.
     */
    #[Override]
    public static function parse(string $value): static
    {
        try {
            // Try to parse using Measurement::parse().
            return parent::parse($value);
        } catch (ValueError $e) {
            // Check for Celsius or Fahrenheit with a degree symbol, e.g. "25°C" or "98.6°F".
            $rxNum = '[-+]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?';
            $pattern = "/^($rxNum)\s*°([CF])$/";

            if (preg_match($pattern, $value, $matches)) {
                return new static((float)$matches[1], $matches[2]);
            }

            // Invalid format.
            throw $e;
        }
    }

    // endregion

    // region Measurement methods

    /**
     * Get the units for Temperature measurements.
     *
     * @return array<string, int> Array of units with allowed prefixes flags.
     */
    #[Override]
    public static function getUnits(): array
    {
        return [
            'K' => self::PREFIX_CODE_METRIC,  // Kelvin
            'C' => 0,  // Celsius
            'F' => 0,  // Fahrenheit
        ];
    }

    /**
     * Get the conversions for Temperature measurements.
     *
     * @return array<array{0: string, 1: string, 2: float, 3?: float}> Array of conversion definitions.
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            ['C', 'K', 1, 273.15],
            ['C', 'F', 1.8, 32],
        ];
    }

    /**
     * Format the unit.
     *
     * @param string $unit
     * @return string
     */
    #[Override]
    public static function formatUnit(string $unit): string
    {
        // Add the degree symbol for Celsius and Fahrenheit units.
        if ($unit === 'C' || $unit === 'F') {
            return '°' . $unit;
        }

        // Otherwise, use the parent method.
        return parent::formatUnit($unit);
    }

    // endregion
}
