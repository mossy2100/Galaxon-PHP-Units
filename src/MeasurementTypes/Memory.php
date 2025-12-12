<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Memory extends Measurement
{
    // region Measurement methods

    /**
     * Get the units for Memory measurements.
     *
     * @return array<string, int> Array of units with allowed prefixes flags.
     */
    #[Override]
    public static function getUnits(): array
    {
        return [
            'B' => self::PREFIX_CODE_LARGE,  // byte
            'b' => self::PREFIX_CODE_LARGE,  // bit
        ];
    }

    /**
     * Get the conversions for Memory measurements.
     *
     * @return array<array{0: string, 1: string, 2: int|float, 3?: int|float}> Array of conversion definitions.
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            // 1 byte = 8 bits
            ['B', 'b', 8]
        ];
    }

    // endregion
}
