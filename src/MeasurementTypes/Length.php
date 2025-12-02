<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Length extends Measurement
{
    // region Physical constants

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

    // region Measurement methods

    /**
     * Get the units for Length measurements.
     *
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            'm' => true,   // metre (accepts metric prefixes)
            'px' => false, // pixel
            'pt' => false, // point
            'in' => false, // inch
            'ft' => false, // foot
            'yd' => false, // yard
            'mi' => false, // mile
            'au' => false, // astronomical unit
            'ly' => false, // light-year
            'pc' => false, // parsec
        ];
    }

    /**
     * Get the conversions for Length measurements.
     *
     * @return array<array<string, string, int|float>>
     */
    public static function getConversions(): array
    {
        return [
            ['ft', 'm', 0.3048],
            ['in', 'px', 96],
            ['in', 'pt', 72],
            ['ft', 'in', 12],
            ['yd', 'ft', 3],
            ['mi', 'yd', 1760],
            ['au', 'm', 149597870700],
            ['ly', 'm', 9460730472580800],
            ['pc', 'au', 648000 / M_PI],
        ];
    }

    // endregion
}
