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
            // Check for formats with degree symbol: "25°C" or "98.6°F"
            $num = '[-+]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?';
            $pattern = "/^($num)\s*°\s*([CF])$/";

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
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            'K' => true,  // Kelvin (accepts metric prefixes)
            'C' => false, // Celsius
            'F' => false, // Fahrenheit
        ];
    }

    /**
     * Get the conversions for Temperature measurements.
     *
     * @return array<array<string, string, int|float, int|float>>
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
    protected static function formatUnit(string $unit): string {
        // Add the degree symbol for Celsius and Fahrenheit units.
        if ($unit === 'C' || $unit === 'F') {
            return '°' . $unit;
        }

        // Otherwise, use the parent method.
        return parent::formatUnit($unit);
    }

    // endregion
}
