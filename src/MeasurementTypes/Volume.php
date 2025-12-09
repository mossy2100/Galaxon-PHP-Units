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
     * @return array<string, int> Array of units with allowed prefixes flags.
     */
    #[Override]
    public static function getUnits(): array
    {
        return [
            'm3'    => self::PREFIXES_METRIC,  // cubic metre
            'L'     => self::PREFIXES_METRIC,  // litre
            'in3'   => 0,  // cubic inch
            'ft3'   => 0,  // cubic foot
            'yd3'   => 0,  // cubic yard
            'gal'   => 0,  // gallon
            'qt'    => 0,  // quart
            'pt'    => 0,  // pint
            'c'     => 0,  // cup
            'fl oz' => 0,  // fluid ounce
            'tbsp'  => 0,  // tablespoon
            'tsp'   => 0,  // teaspoon
        ];
    }

    /**
     * Get the conversions for Volume measurements.
     *
     * @return array<array{0: string, 1: string, 2: int|float, 3?: int|float}> Array of conversion definitions.
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            // Metric.
            ['m3', 'L', 1000],
            // Metric-imperial bridge.
            ['in3', 'mL', 16.387064],
            // Imperial.
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
