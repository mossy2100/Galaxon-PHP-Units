<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Area extends Measurement
{
    // region Extraction methods

    /**
     * Get the units for Area measurements.
     *
     * @return array<string, int> Array of units with allowed prefixes flags.
     */
    #[Override]
    public static function getUnits(): array
    {
        return [
            'm2'  => self::PREFIX_CODE_METRIC,  // square metre
            'ha'  => 0,  // hectare
            'ac'  => 0,  // acre
            'mi2' => 0,  // square mile
            'yd2' => 0,  // square yard
            'ft2' => 0,  // square foot
            'in2' => 0,  // square inch
        ];
    }

    /**
     * Get the conversions for Area measurements.
     *
     * @return array<array{0: string, 1: string, 2: float, 3?: float}> Array of conversion definitions.
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            // Metric.
            ['ha', 'm2', 10000],
            // Metric-imperial bridge.
            ['ac', 'm2', 4046.8564224],
            // Imperial.
            ['mi2', 'ac', 640],
            ['ac', 'yd2', 4840],
            ['yd2', 'ft2', 9],
            ['ft2', 'in2', 144],
        ];
    }

    // endregion
}
