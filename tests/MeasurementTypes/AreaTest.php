<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests\MeasurementTypes;

use Galaxon\Units\MeasurementTypes\Area;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Area measurement class.
 *
 * These tests focus on verifying that prefix multipliers and exponents work together correctly
 * for squared units (m2, km2, cm2, etc.).
 */
#[CoversClass(Area::class)]
final class AreaTest extends TestCase
{
    // region Constructor and basic unit tests

    /**
     * Test constructor with base unit.
     */
    public function testConstructorWithBaseUnit(): void
    {
        $area = new Area(100, 'm2');

        $this->assertSame(100.0, $area->value);
        $this->assertSame('m2', $area->unit);
    }

    /**
     * Test constructor with prefixed unit.
     */
    public function testConstructorWithPrefixedUnit(): void
    {
        $area = new Area(5, 'km2');

        $this->assertSame(5.0, $area->value);
        $this->assertSame('km2', $area->unit);
    }

    // endregion

    // region Prefix multiplier with exponent tests

    /**
     * Test km2 to m2 conversion (prefix multiplier squared).
     *
     * 1 km = 1000 m, so 1 km2 = 1000^2 m2 = 1,000,000 m2
     */
    public function testKm2ToM2Conversion(): void
    {
        $area = new Area(1, 'km2');

        $result = $area->to('m2');

        $this->assertEqualsWithDelta(1e6, $result->value, 1e-6);
        $this->assertSame('m2', $result->unit);
    }

    /**
     * Test m2 to km2 conversion (inverse prefix multiplier squared).
     *
     * 1,000,000 m2 = 1 km2
     */
    public function testM2ToKm2Conversion(): void
    {
        $area = new Area(1e6, 'm2');

        $result = $area->to('km2');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
        $this->assertSame('km2', $result->unit);
    }

    /**
     * Test cm2 to m2 conversion (small prefix multiplier squared).
     *
     * 1 cm = 0.01 m, so 1 cm2 = 0.01^2 m2 = 0.0001 m2
     */
    public function testCm2ToM2Conversion(): void
    {
        $area = new Area(1, 'cm2');

        $result = $area->to('m2');

        $this->assertEqualsWithDelta(1e-4, $result->value, 1e-14);
        $this->assertSame('m2', $result->unit);
    }

    /**
     * Test m2 to cm2 conversion.
     *
     * 1 m2 = 10,000 cm2
     */
    public function testM2ToCm2Conversion(): void
    {
        $area = new Area(1, 'm2');

        $result = $area->to('cm2');

        $this->assertEqualsWithDelta(1e4, $result->value, 1e-6);
        $this->assertSame('cm2', $result->unit);
    }

    /**
     * Test mm2 to m2 conversion.
     *
     * 1 mm = 0.001 m, so 1 mm2 = 0.001^2 m2 = 1e-6 m2
     */
    public function testMm2ToM2Conversion(): void
    {
        $area = new Area(1, 'mm2');

        $result = $area->to('m2');

        $this->assertEqualsWithDelta(1e-6, $result->value, 1e-16);
        $this->assertSame('m2', $result->unit);
    }

    /**
     * Test m2 to mm2 conversion.
     *
     * 1 m2 = 1,000,000 mm2
     */
    public function testM2ToMm2Conversion(): void
    {
        $area = new Area(1, 'm2');

        $result = $area->to('mm2');

        $this->assertEqualsWithDelta(1e6, $result->value, 1e-4);
        $this->assertSame('mm2', $result->unit);
    }

    /**
     * Test km2 to cm2 conversion (prefix to prefix with exponent).
     *
     * 1 km2 = 1e10 cm2 (1000/0.01)^2 = 1e10
     */
    public function testKm2ToCm2Conversion(): void
    {
        $area = new Area(1, 'km2');

        $result = $area->to('cm2');

        $this->assertEqualsWithDelta(1e10, $result->value, 1e0);
        $this->assertSame('cm2', $result->unit);
    }

    /**
     * Test cm2 to km2 conversion.
     *
     * 1e10 cm2 = 1 km2
     */
    public function testCm2ToKm2Conversion(): void
    {
        $area = new Area(1e10, 'cm2');

        $result = $area->to('km2');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
        $this->assertSame('km2', $result->unit);
    }

    // endregion

    // region Metric to imperial conversion tests

    /**
     * Test hectare to m2 conversion.
     *
     * 1 ha = 10,000 m2
     */
    public function testHectareToM2Conversion(): void
    {
        $area = new Area(1, 'ha');

        $result = $area->to('m2');

        $this->assertEqualsWithDelta(10000.0, $result->value, 1e-6);
        $this->assertSame('m2', $result->unit);
    }

    /**
     * Test hectare to km2 conversion.
     *
     * 1 ha = 0.01 km2
     */
    public function testHectareToKm2Conversion(): void
    {
        $area = new Area(1, 'ha');

        $result = $area->to('km2');

        $this->assertEqualsWithDelta(0.01, $result->value, 1e-10);
        $this->assertSame('km2', $result->unit);
    }

    /**
     * Test km2 to hectare conversion.
     *
     * 1 km2 = 100 ha
     */
    public function testKm2ToHectareConversion(): void
    {
        $area = new Area(1, 'km2');

        $result = $area->to('ha');

        $this->assertEqualsWithDelta(100.0, $result->value, 1e-6);
        $this->assertSame('ha', $result->unit);
    }

    /**
     * Test acre to m2 conversion.
     *
     * 1 ac = 4046.8564224 m2
     */
    public function testAcreToM2Conversion(): void
    {
        $area = new Area(1, 'ac');

        $result = $area->to('m2');

        $this->assertEqualsWithDelta(4046.8564224, $result->value, 1e-6);
        $this->assertSame('m2', $result->unit);
    }

    /**
     * Test ft2 to m2 conversion.
     */
    public function testFt2ToM2Conversion(): void
    {
        $area = new Area(1, 'ft2');

        $result = $area->to('m2');

        // 1 ft2 ≈ 0.09290304 m2
        $this->assertEqualsWithDelta(0.09290304, $result->value, 1e-6);
        $this->assertSame('m2', $result->unit);
    }

    /**
     * Test in2 to cm2 conversion.
     */
    public function testIn2ToCm2Conversion(): void
    {
        $area = new Area(1, 'in2');

        $result = $area->to('cm2');

        // 1 in2 ≈ 6.4516 cm2
        $this->assertEqualsWithDelta(6.4516, $result->value, 1e-3);
        $this->assertSame('cm2', $result->unit);
    }

    // endregion

    // region Round-trip conversion tests

    /**
     * Test round-trip conversion preserves value (km2 -> m2 -> km2).
     */
    public function testRoundTripKm2M2Km2(): void
    {
        $original = new Area(123.456, 'km2');

        $result = $original->to('m2')->to('km2');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-10);
    }

    /**
     * Test round-trip conversion through multiple prefixes.
     */
    public function testRoundTripMultiplePrefixes(): void
    {
        $original = new Area(1, 'km2');

        $result = $original->to('m2')->to('cm2')->to('mm2')->to('m2')->to('km2');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-10);
    }

    /**
     * Test round-trip through metric and imperial.
     */
    public function testRoundTripMetricImperial(): void
    {
        $original = new Area(5.5, 'km2');

        $result = $original->to('ac')->to('ft2')->to('m2')->to('km2');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-6);
    }

    // endregion

    // region Arithmetic with prefixed units tests

    /**
     * Test addition with different prefixed units.
     */
    public function testAddDifferentPrefixes(): void
    {
        $a = new Area(1, 'km2');
        $b = new Area(500000, 'm2');

        $result = $a->add($b);

        // 1 km2 + 500000 m2 = 1 km2 + 0.5 km2 = 1.5 km2
        $this->assertEqualsWithDelta(1.5, $result->value, 1e-10);
        $this->assertSame('km2', $result->unit);
    }

    /**
     * Test subtraction with different prefixed units.
     */
    public function testSubDifferentPrefixes(): void
    {
        $a = new Area(1, 'm2');
        $b = new Area(5000, 'cm2');

        $result = $a->sub($b);

        // 1 m2 - 5000 cm2 = 1 m2 - 0.5 m2 = 0.5 m2
        $this->assertEqualsWithDelta(0.5, $result->value, 1e-10);
        $this->assertSame('m2', $result->unit);
    }

    // endregion
}
