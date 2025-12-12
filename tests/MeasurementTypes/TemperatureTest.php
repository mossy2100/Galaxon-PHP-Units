<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests\MeasurementTypes;

use Galaxon\Units\MeasurementTypes\Temperature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Tests for Temperature measurement class.
 *
 * Temperature uses offset conversions (not just multipliers) for C/F/K.
 */
#[CoversClass(Temperature::class)]
final class TemperatureTest extends TestCase
{
    // region Constructor and basic unit tests

    /**
     * Test constructor with Celsius.
     */
    public function testConstructorWithCelsius(): void
    {
        $temp = new Temperature(25, 'C');

        $this->assertSame(25.0, $temp->value);
        $this->assertSame('C', $temp->unit);
    }

    /**
     * Test constructor with Fahrenheit.
     */
    public function testConstructorWithFahrenheit(): void
    {
        $temp = new Temperature(98.6, 'F');

        $this->assertSame(98.6, $temp->value);
        $this->assertSame('F', $temp->unit);
    }

    /**
     * Test constructor with Kelvin.
     */
    public function testConstructorWithKelvin(): void
    {
        $temp = new Temperature(273.15, 'K');

        $this->assertSame(273.15, $temp->value);
        $this->assertSame('K', $temp->unit);
    }

    // endregion

    // region Celsius to Fahrenheit conversion tests

    /**
     * Test 0°C = 32°F (freezing point of water).
     */
    public function testFreezingPointCToF(): void
    {
        $temp = new Temperature(0, 'C');

        $result = $temp->to('F');

        $this->assertEqualsWithDelta(32.0, $result->value, 1e-10);
    }

    /**
     * Test 100°C = 212°F (boiling point of water).
     */
    public function testBoilingPointCToF(): void
    {
        $temp = new Temperature(100, 'C');

        $result = $temp->to('F');

        $this->assertEqualsWithDelta(212.0, $result->value, 1e-10);
    }

    /**
     * Test -40°C = -40°F (crossover point).
     */
    public function testCrossoverPointCToF(): void
    {
        $temp = new Temperature(-40, 'C');

        $result = $temp->to('F');

        $this->assertEqualsWithDelta(-40.0, $result->value, 1e-10);
    }

    /**
     * Test 32°F = 0°C.
     */
    public function testFreezingPointFToC(): void
    {
        $temp = new Temperature(32, 'F');

        $result = $temp->to('C');

        $this->assertEqualsWithDelta(0.0, $result->value, 1e-10);
    }

    /**
     * Test 212°F = 100°C.
     */
    public function testBoilingPointFToC(): void
    {
        $temp = new Temperature(212, 'F');

        $result = $temp->to('C');

        $this->assertEqualsWithDelta(100.0, $result->value, 1e-10);
    }

    // endregion

    // region Celsius to Kelvin conversion tests

    /**
     * Test 0°C = 273.15 K.
     */
    public function testFreezingPointCToK(): void
    {
        $temp = new Temperature(0, 'C');

        $result = $temp->to('K');

        $this->assertEqualsWithDelta(273.15, $result->value, 1e-10);
    }

    /**
     * Test 100°C = 373.15 K.
     */
    public function testBoilingPointCToK(): void
    {
        $temp = new Temperature(100, 'C');

        $result = $temp->to('K');

        $this->assertEqualsWithDelta(373.15, $result->value, 1e-10);
    }

    /**
     * Test -273.15°C = 0 K (absolute zero).
     */
    public function testAbsoluteZeroCToK(): void
    {
        $temp = new Temperature(-273.15, 'C');

        $result = $temp->to('K');

        $this->assertEqualsWithDelta(0.0, $result->value, 1e-10);
    }

    /**
     * Test 273.15 K = 0°C.
     */
    public function testFreezingPointKToC(): void
    {
        $temp = new Temperature(273.15, 'K');

        $result = $temp->to('C');

        $this->assertEqualsWithDelta(0.0, $result->value, 1e-10);
    }

    // endregion

    // region Fahrenheit to Kelvin conversion tests

    /**
     * Test 32°F = 273.15 K.
     */
    public function testFreezingPointFToK(): void
    {
        $temp = new Temperature(32, 'F');

        $result = $temp->to('K');

        $this->assertEqualsWithDelta(273.15, $result->value, 1e-10);
    }

    /**
     * Test 0 K = -459.67°F (absolute zero).
     */
    public function testAbsoluteZeroKToF(): void
    {
        $temp = new Temperature(0, 'K');

        $result = $temp->to('F');

        $this->assertEqualsWithDelta(-459.67, $result->value, 0.01);
    }

    // endregion

    // region Kelvin prefix tests

    /**
     * Test millikelvin to Kelvin conversion.
     */
    public function testMillikelvinToKelvinConversion(): void
    {
        $temp = new Temperature(1000, 'mK');

        $result = $temp->to('K');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    // endregion

    // region Parse method tests

    /**
     * Test parse with standard format.
     */
    public function testParseStandardFormat(): void
    {
        $temp = Temperature::parse('25C');

        $this->assertEqualsWithDelta(25.0, $temp->value, 1e-10);
        $this->assertSame('C', $temp->unit);
    }

    /**
     * Test parse with degree symbol (Celsius).
     */
    public function testParseWithDegreeSymbolCelsius(): void
    {
        $temp = Temperature::parse('25°C');

        $this->assertEqualsWithDelta(25.0, $temp->value, 1e-10);
        $this->assertSame('C', $temp->unit);
    }

    /**
     * Test parse with degree symbol (Fahrenheit).
     */
    public function testParseWithDegreeSymbolFahrenheit(): void
    {
        $temp = Temperature::parse('98.6°F');

        $this->assertEqualsWithDelta(98.6, $temp->value, 1e-10);
        $this->assertSame('F', $temp->unit);
    }

    /**
     * Test parse with negative value and degree symbol.
     */
    public function testParseNegativeWithDegreeSymbol(): void
    {
        $temp = Temperature::parse('-40°C');

        $this->assertEqualsWithDelta(-40.0, $temp->value, 1e-10);
        $this->assertSame('C', $temp->unit);
    }

    /**
     * Test parse with Kelvin (no degree symbol).
     */
    public function testParseKelvin(): void
    {
        $temp = Temperature::parse('273.15K');

        $this->assertEqualsWithDelta(273.15, $temp->value, 1e-10);
        $this->assertSame('K', $temp->unit);
    }

    /**
     * Test parse throws for invalid format.
     */
    public function testParseThrowsForInvalidFormat(): void
    {
        $this->expectException(ValueError::class);

        Temperature::parse('invalid');
    }

    // endregion

    // region Format tests

    /**
     * Test format adds degree symbol for Celsius.
     */
    public function testFormatAddsDegreeSymbolForCelsius(): void
    {
        $temp = new Temperature(25, 'C');

        $this->assertSame('25°C', (string)$temp);
    }

    /**
     * Test format adds degree symbol for Fahrenheit.
     */
    public function testFormatAddsDegreeSymbolForFahrenheit(): void
    {
        $temp = new Temperature(98.6, 'F');

        $this->assertSame('98.6°F', (string)$temp);
    }

    /**
     * Test format does not add degree symbol for Kelvin.
     */
    public function testFormatNoSymbolForKelvin(): void
    {
        $temp = new Temperature(273.15, 'K');

        $this->assertSame('273.15K', (string)$temp);
    }

    // endregion

    // region Round-trip conversion tests

    /**
     * Test round-trip conversion preserves value.
     */
    public function testRoundTripConversion(): void
    {
        $original = new Temperature(25.0, 'C');

        $result = $original->to('F')->to('K')->to('C');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-10);
    }

    // endregion
}
