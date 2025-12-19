<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Length extends Measurement
{
    // region Factory methods

    /**
     * Get the Planck length.
     *
     * @return self Length object representing the Planck length (1.616255×10⁻³⁵ m).
     */
    public static function planckLength(): self
    {
        return new self(1.616255e-35, 'm');
    }

    // endregion

    // region Extraction methods

    /**
     * Get the units for Length measurements.
     *
     * @return array<string, int> Array of units with allowed prefixes flags.
     */
    #[Override]
    public static function getUnits(): array
    {
        return [
            'm'  => self::PREFIX_CODE_METRIC,  // metre
            'px' => 0,  // pixel
            'pt' => 0,  // point
            'in' => 0,  // inch
            'ft' => 0,  // foot
            'yd' => 0,  // yard
            'mi' => 0,  // mile
            'au' => 0,  // astronomical unit
            'ly' => 0,  // light-year
            'pc' => 0,  // parsec
        ];
    }

    /**
     * Get the conversions for Length measurements.
     *
     * @return array<array{0: string, 1: string, 2: float, 3?: float}> Array of conversion definitions.
     */
    public static function getConversions(): array
    {
        return [
            // Metric-imperial bridge.
            ['in', 'mm', 25.4],
            // Imperial.
            ['in', 'px', 96],
            ['in', 'pt', 72],
            ['ft', 'in', 12],
            ['yd', 'ft', 3],
            ['mi', 'yd', 1760],
            // Astronomical.
            ['au', 'm', 149597870700],
            ['ly', 'm', 9460730472580800],
            ['pc', 'au', 648000 / M_PI],
        ];
    }

    // endregion
}
