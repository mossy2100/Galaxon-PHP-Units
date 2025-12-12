<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests\MeasurementTypes;

use Galaxon\Units\MeasurementTypes\Length;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Length measurement class.
 */
#[CoversClass(Length::class)]
final class LengthTest extends TestCase
{
    // region Constructor and basic unit tests

    /**
     * Test constructor with base unit.
     */
    public function testConstructorWithBaseUnit(): void
    {
        $length = new Length(100, 'm');

        $this->assertSame(100.0, $length->value);
        $this->assertSame('m', $length->unit);
    }

    /**
     * Test constructor with prefixed unit.
     */
    public function testConstructorWithPrefixedUnit(): void
    {
        $length = new Length(5, 'km');

        $this->assertSame(5.0, $length->value);
        $this->assertSame('km', $length->unit);
    }

    // endregion

    // region Metric prefix conversion tests

    /**
     * Test km to m conversion.
     */
    public function testKmToMConversion(): void
    {
        $length = new Length(1, 'km');

        $result = $length->to('m');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    /**
     * Test m to km conversion.
     */
    public function testMToKmConversion(): void
    {
        $length = new Length(1000, 'm');

        $result = $length->to('km');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    /**
     * Test cm to m conversion.
     */
    public function testCmToMConversion(): void
    {
        $length = new Length(100, 'cm');

        $result = $length->to('m');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    /**
     * Test mm to m conversion.
     */
    public function testMmToMConversion(): void
    {
        $length = new Length(1000, 'mm');

        $result = $length->to('m');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    // endregion

    // region Metric-imperial conversion tests

    /**
     * Test inch to mm conversion (exact definition).
     */
    public function testInchToMmConversion(): void
    {
        $length = new Length(1, 'in');

        $result = $length->to('mm');

        $this->assertEqualsWithDelta(25.4, $result->value, 1e-10);
    }

    /**
     * Test foot to inch conversion.
     */
    public function testFootToInchConversion(): void
    {
        $length = new Length(1, 'ft');

        $result = $length->to('in');

        $this->assertEqualsWithDelta(12.0, $result->value, 1e-10);
    }

    /**
     * Test yard to foot conversion.
     */
    public function testYardToFootConversion(): void
    {
        $length = new Length(1, 'yd');

        $result = $length->to('ft');

        $this->assertEqualsWithDelta(3.0, $result->value, 1e-10);
    }

    /**
     * Test mile to yard conversion.
     */
    public function testMileToYardConversion(): void
    {
        $length = new Length(1, 'mi');

        $result = $length->to('yd');

        $this->assertEqualsWithDelta(1760.0, $result->value, 1e-10);
    }

    /**
     * Test mile to km conversion.
     */
    public function testMileToKmConversion(): void
    {
        $length = new Length(1, 'mi');

        $result = $length->to('km');

        // 1 mi = 1760 yd * 3 ft * 12 in * 25.4 mm / 1e6 = 1.609344 km
        $this->assertEqualsWithDelta(1.609344, $result->value, 1e-6);
    }

    /**
     * Test km to mile conversion.
     */
    public function testKmToMileConversion(): void
    {
        $length = new Length(1.609344, 'km');

        $result = $length->to('mi');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-6);
    }

    // endregion

    // region Typography unit tests

    /**
     * Test inch to pixel conversion.
     */
    public function testInchToPixelConversion(): void
    {
        $length = new Length(1, 'in');

        $result = $length->to('px');

        $this->assertEqualsWithDelta(96.0, $result->value, 1e-10);
    }

    /**
     * Test inch to point conversion.
     */
    public function testInchToPointConversion(): void
    {
        $length = new Length(1, 'in');

        $result = $length->to('pt');

        $this->assertEqualsWithDelta(72.0, $result->value, 1e-10);
    }

    // endregion

    // region Astronomical unit tests

    /**
     * Test AU to m conversion.
     */
    public function testAuToMConversion(): void
    {
        $length = new Length(1, 'au');

        $result = $length->to('m');

        $this->assertEqualsWithDelta(149597870700.0, $result->value, 1);
    }

    /**
     * Test light-year to m conversion.
     */
    public function testLightYearToMConversion(): void
    {
        $length = new Length(1, 'ly');

        $result = $length->to('m');

        $this->assertEqualsWithDelta(9460730472580800.0, $result->value, 1);
    }

    /**
     * Test parsec to AU conversion.
     */
    public function testParsecToAuConversion(): void
    {
        $length = new Length(1, 'pc');

        $result = $length->to('au');

        // 1 pc = 648000/π AU ≈ 206264.806 AU
        $this->assertEqualsWithDelta(648000 / M_PI, $result->value, 1e-3);
    }

    // endregion

    // region Physical constants tests

    /**
     * Test Planck length constant.
     */
    public function testPlanckLength(): void
    {
        $planck = Length::planckLength();

        $this->assertEqualsWithDelta(1.616255e-35, $planck->value, 1e-40);
        $this->assertSame('m', $planck->unit);
    }

    // endregion

    // region Round-trip conversion tests

    /**
     * Test round-trip conversion preserves value.
     */
    public function testRoundTripConversion(): void
    {
        $original = new Length(123.456, 'km');

        $result = $original->to('mi')->to('ft')->to('in')->to('mm')->to('m')->to('km');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-10);
    }

    // endregion
}
