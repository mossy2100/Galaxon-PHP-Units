<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Area extends Measurement
{
    // region Measurement methods

    /**
     * Get the units for Area measurements.
     *
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            'm2' => true,   // square metre (accepts metric prefixes)
            'ha' => false,  // hectare
            'ac' => false,  // acre
            'mi2' => false, // square mile
            'yd2' => false, // square yard
            'ft2' => false, // square foot
            'in2' => false, // square inch
        ];
    }

    /**
     * Get the valid prefixes for Area measurements.
     *
     * @return array<string, int|float>
     */
    #[Override]
    public static function getPrefixes(): array {
        // Square the metric prefixes to get the valid Area prefixes.
        $validPrefixes = self::METRIC_PREFIXES;
        foreach ($validPrefixes as $prefix => $factor) {
            $validPrefixes[$prefix] = $factor * $factor;
        }
        return $validPrefixes;
    }

    /**
     * Get the conversions for Area measurements.
     *
     * @return array<array<string, string, int|float>>
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            ['ha', 'm2', 10000],
            ['ac', 'm2', 4046.8564224],
            ['mi2', 'ac', 640],
            ['ac', 'yd2', 4840],
            ['yd2', 'ft2', 9],
            ['ft2', 'in2', 144],
        ];
    }

    // endregion
}
