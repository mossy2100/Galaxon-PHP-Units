<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Units\Measurement;
use Override;

class Memory extends Measurement
{
    /**
     * Binary prefixes for memory measurements.
     *
     * @var array<string, int|float>
     */
    public const array BINARY_PREFIXES = [
        'Ki' => 2 ** 10, // kibi
        'Mi' => 2 ** 20, // mebi
        'Gi' => 2 ** 30, // gibi
        'Ti' => 2 ** 40, // tebi
        'Pi' => 2 ** 50, // pebi
        'Ei' => 2 ** 60, // exbi
        // These next two values will be represented as floats because they exceed PHP_INT_MAX, but they will still be
        // represented exactly because they're powers of 2.
        'Zi' => 2 ** 70, // zebi
        'Yi' => 2 ** 80, // yobi
    ];

    // region Measurement methods

    /**
     * Get the units for Memory measurements.
     *
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            'B' => true,  // byte (accepts both metric and binary prefixes)
            'b' => false, // bit
        ];
    }

    /**
     * Get the valid prefixes for memory measurements.
     *
     * @return array<string, int|float> Array of valid prefixes.
     */
    #[Override]
    public static function getPrefixes(): array {
        // With memory, both metric and binary prefixes are valid.
        return array_merge(self::METRIC_PREFIXES, self::BINARY_PREFIXES);
    }

    /**
     * Get the conversions for Memory measurements.
     *
     * @return array<int, array> Array of conversion definitions.
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
