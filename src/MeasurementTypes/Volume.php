<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Volume extends Measurement
{
    // region Measurement methods

    /**
     * Get the units for Volume measurements.
     *
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            'm3' => true,    // cubic metre (accepts metric prefixes)
            'L' => true,     // litre (accepts metric prefixes)
            'in3' => false,  // cubic inch
            'ft3' => false,  // cubic foot
            'yd3' => false,  // cubic yard
            'gal' => false,  // gallon
            'qt' => false,   // quart
            'pt' => false,   // pint
            'c' => false,    // cup
            'fl oz' => false, // fluid ounce
            'tbsp' => false, // tablespoon
            'tsp' => false,  // teaspoon
        ];
    }

    /**
     * Get the valid prefixes for Volume measurements.
     *
     * @return array<string, int|float>
     */
    #[Override]
    public static function getPrefixes(): array {
        // Cube the metric prefixes to get the valid Volume prefixes.
        $validPrefixes = self::METRIC_PREFIXES;
        foreach ($validPrefixes as $prefix => $factor) {
            $validPrefixes[$prefix] = $factor ** 3;
        }
        return $validPrefixes;
    }

    /**
     * Get the conversions for Volume measurements.
     *
     * @return array<array<string, string, int|float>>
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            ['m3', 'L', 1000],
            ['in3', 'mL', 16.387064],
            ['ft3', 'in3', 1728],
            ['yd3', 'ft3', 27],
            ['gal', 'qt', 4],
            ['gal', 'in3', 231],
            ['qt', 'pt', 2],
            ['pt', 'c', 2],
            ['c', 'fl oz', 8],
            ['fl oz', 'tbsp', 2],
            ['tbsp', 'tsp', 3]
        ];
    }

    // endregion
}
