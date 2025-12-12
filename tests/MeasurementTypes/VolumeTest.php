<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests\MeasurementTypes;

use Galaxon\Units\MeasurementTypes\Volume;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Volume measurement class.
 *
 * Volume includes cubic units (m3, in3, ft3) and liquid units (L, gal, etc.).
 * Cubic units use exponent 3, so prefix multipliers are cubed.
 */
#[CoversClass(Volume::class)]
final class VolumeTest extends TestCase
{
    // region Constructor and basic unit tests

    /**
     * Test constructor with cubic metre.
     */
    public function testConstructorWithCubicMetre(): void
    {
        $volume = new Volume(1, 'm3');

        $this->assertSame(1.0, $volume->value);
        $this->assertSame('m3', $volume->unit);
    }

    /**
     * Test constructor with litre.
     */
    public function testConstructorWithLitre(): void
    {
        $volume = new Volume(1000, 'L');

        $this->assertSame(1000.0, $volume->value);
        $this->assertSame('L', $volume->unit);
    }

    // endregion

    // region Cubic metre prefix conversion tests (prefix cubed)

    /**
     * Test m3 to L conversion.
     *
     * 1 m³ = 1000 L
     */
    public function testM3ToLConversion(): void
    {
        $volume = new Volume(1, 'm3');

        $result = $volume->to('L');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    /**
     * Test L to m3 conversion.
     */
    public function testLToM3Conversion(): void
    {
        $volume = new Volume(1000, 'L');

        $result = $volume->to('m3');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    /**
     * Test cm3 to m3 conversion (prefix cubed).
     *
     * 1 cm = 0.01 m, so 1 cm³ = 0.01³ m³ = 1e-6 m³
     */
    public function testCm3ToM3Conversion(): void
    {
        $volume = new Volume(1, 'cm3');

        $result = $volume->to('m3');

        $this->assertEqualsWithDelta(1e-6, $result->value, 1e-16);
    }

    /**
     * Test m3 to cm3 conversion.
     *
     * 1 m³ = 1,000,000 cm³
     */
    public function testM3ToCm3Conversion(): void
    {
        $volume = new Volume(1, 'm3');

        $result = $volume->to('cm3');

        $this->assertEqualsWithDelta(1e6, $result->value, 1e-4);
    }

    /**
     * Test mm3 to m3 conversion (prefix cubed).
     *
     * 1 mm = 0.001 m, so 1 mm³ = 0.001³ m³ = 1e-9 m³
     */
    public function testMm3ToM3Conversion(): void
    {
        $volume = new Volume(1, 'mm3');

        $result = $volume->to('m3');

        $this->assertEqualsWithDelta(1e-9, $result->value, 1e-19);
    }

    /**
     * Test cm3 to mL conversion (1 cm³ = 1 mL).
     */
    public function testCm3ToMlConversion(): void
    {
        $volume = new Volume(1, 'cm3');

        $result = $volume->to('mL');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    // endregion

    // region Litre prefix conversion tests

    /**
     * Test mL to L conversion.
     */
    public function testMlToLConversion(): void
    {
        $volume = new Volume(1000, 'mL');

        $result = $volume->to('L');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    /**
     * Test kL to L conversion.
     */
    public function testKlToLConversion(): void
    {
        $volume = new Volume(1, 'kL');

        $result = $volume->to('L');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    // endregion

    // region Imperial cubic conversion tests

    /**
     * Test ft3 to in3 conversion.
     *
     * 1 ft³ = 12³ in³ = 1728 in³
     */
    public function testFt3ToIn3Conversion(): void
    {
        $volume = new Volume(1, 'ft3');

        $result = $volume->to('in3');

        $this->assertEqualsWithDelta(1728.0, $result->value, 1e-10);
    }

    /**
     * Test yd3 to ft3 conversion.
     *
     * 1 yd³ = 3³ ft³ = 27 ft³
     */
    public function testYd3ToFt3Conversion(): void
    {
        $volume = new Volume(1, 'yd3');

        $result = $volume->to('ft3');

        $this->assertEqualsWithDelta(27.0, $result->value, 1e-10);
    }

    /**
     * Test in3 to mL conversion.
     *
     * 1 in³ = 16.387064 mL
     */
    public function testIn3ToMlConversion(): void
    {
        $volume = new Volume(1, 'in3');

        $result = $volume->to('mL');

        $this->assertEqualsWithDelta(16.387064, $result->value, 1e-6);
    }

    // endregion

    // region US liquid measure conversion tests

    /**
     * Test gallon to quart conversion.
     */
    public function testGallonToQuartConversion(): void
    {
        $volume = new Volume(1, 'gal');

        $result = $volume->to('qt');

        $this->assertEqualsWithDelta(4.0, $result->value, 1e-10);
    }

    /**
     * Test gallon to in3 conversion.
     *
     * 1 US gallon = 231 in³
     */
    public function testGallonToIn3Conversion(): void
    {
        $volume = new Volume(1, 'gal');

        $result = $volume->to('in3');

        $this->assertEqualsWithDelta(231.0, $result->value, 1e-10);
    }

    /**
     * Test gallon to litre conversion.
     *
     * 1 US gallon = 231 in³ * 16.387064 mL/in³ = 3785.411784 mL ≈ 3.785 L
     */
    public function testGallonToLitreConversion(): void
    {
        $volume = new Volume(1, 'gal');

        $result = $volume->to('L');

        $this->assertEqualsWithDelta(3.785411784, $result->value, 1e-6);
    }

    /**
     * Test quart to pint conversion.
     */
    public function testQuartToPintConversion(): void
    {
        $volume = new Volume(1, 'qt');

        $result = $volume->to('pt');

        $this->assertEqualsWithDelta(2.0, $result->value, 1e-10);
    }

    /**
     * Test pint to cup conversion.
     */
    public function testPintToCupConversion(): void
    {
        $volume = new Volume(1, 'pt');

        $result = $volume->to('c');

        $this->assertEqualsWithDelta(2.0, $result->value, 1e-10);
    }

    /**
     * Test cup to fluid ounce conversion.
     */
    public function testCupToFluidOunceConversion(): void
    {
        $volume = new Volume(1, 'c');

        $result = $volume->to('floz');

        $this->assertEqualsWithDelta(8.0, $result->value, 1e-10);
    }

    /**
     * Test fluid ounce to tablespoon conversion.
     */
    public function testFluidOunceToTablespoonConversion(): void
    {
        $volume = new Volume(1, 'floz');

        $result = $volume->to('tbsp');

        $this->assertEqualsWithDelta(2.0, $result->value, 1e-10);
    }

    /**
     * Test tablespoon to teaspoon conversion.
     */
    public function testTablespoonToTeaspoonConversion(): void
    {
        $volume = new Volume(1, 'tbsp');

        $result = $volume->to('tsp');

        $this->assertEqualsWithDelta(3.0, $result->value, 1e-10);
    }

    // endregion

    // region Round-trip conversion tests

    /**
     * Test round-trip conversion preserves value (metric).
     */
    public function testRoundTripMetricConversion(): void
    {
        $original = new Volume(123.456, 'L');

        $result = $original->to('m3')->to('cm3')->to('mL')->to('L');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-10);
    }

    /**
     * Test round-trip conversion preserves value (imperial).
     */
    public function testRoundTripImperialConversion(): void
    {
        $original = new Volume(5.5, 'gal');

        $result = $original->to('qt')->to('pt')->to('c')->to('floz')->to('tbsp')->to('tsp')
            ->to('mL')->to('L')->to('gal');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-6);
    }

    // endregion
}
