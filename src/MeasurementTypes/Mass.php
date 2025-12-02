<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Mass extends Measurement
{
    // region Physical constants

    /**
     * Get the mass of an electron.
     *
     * @return self Mass object representing the electron rest mass (9.1093837015×10⁻³¹ kg).
     */
    public static function electronMass(): self
    {
        return new self(9.1093837015e-31, 'kg');
    }

    /**
     * Get the mass of a proton.
     *
     * @return self Mass object representing the proton rest mass (1.67262192369×10⁻²⁷ kg).
     */
    public static function protonMass(): self
    {
        return new self(1.67262192369e-27, 'kg');
    }

    /**
     * Get the mass of a neutron.
     *
     * @return self Mass object representing the neutron rest mass (1.67492749804×10⁻²⁷ kg).
     */
    public static function neutronMass(): self
    {
        return new self(1.67492749804e-27, 'kg');
    }

    // endregion

    // region Measurement methods

    /**
     * Get the units for Mass measurements.
     *
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            'g' => true,    // gram (accepts metric prefixes)
            't' => false,   // tonne
            'gr' => false,  // grain
            'oz' => false,  // ounce
            'lb' => false,  // pound
            'st' => false,  // stone
            'cwt' => false, // hundredweight
            'ton' => false, // ton
        ];
    }

    /**
     * Get the conversions for Mass measurements.
     *
     * @return array<array<string, string, int|float>>
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            ['t', 'g', 1e6],
            ['lb', 'g', 453.59237],
            ['lb', 'oz', 16],
            ['st', 'lb', 14],
            // Use US short ton by default.
            ['ton', 'lb', 2000],
        ];
    }

    /**
     * Use British (imperial) ton instead of US ton.
     *
     * Default:
     * - 1 ton = 2000 lb (short ton)
     *
     * After calling this method:
     * - 1 ton = 2240 lb (long ton)
     */
    public static function useBritishUnits(): void {
        // Update the conversion from ton to lb.
        self::getUnitConverter()->addConversion('ton', 'lb', 2240);
    }

    // endregion
}
