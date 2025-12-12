<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests;

use DivisionByZeroError;
use Galaxon\Units\Measurement;
use Galaxon\Units\MeasurementTypes\Area;
use Galaxon\Units\MeasurementTypes\Length;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;
use TypeError;
use ValueError;

/**
 * Test class for Measurement abstract class.
 *
 * Uses concrete implementations (Length, Area, Mass) to test inherited functionality.
 */
#[CoversClass(Measurement::class)]
final class MeasurementTest extends TestCase
{
    // region Constructor tests

    /**
     * Test constructor with valid values.
     */
    public function testConstructorWithValidValues(): void
    {
        $length = new Length(100, 'm');

        $this->assertSame(100.0, $length->value);
        $this->assertSame('m', $length->unit);
    }

    /**
     * Test constructor with zero value.
     */
    public function testConstructorWithZero(): void
    {
        $length = new Length(0, 'm');

        $this->assertSame(0.0, $length->value);
    }

    /**
     * Test constructor with negative value.
     */
    public function testConstructorWithNegativeValue(): void
    {
        $length = new Length(-50, 'km');

        $this->assertSame(-50.0, $length->value);
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

    /**
     * Test constructor throws for infinity.
     */
    public function testConstructorThrowsForInfinity(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('cannot be ±INF or NAN');

        new Length(INF, 'm');
    }

    /**
     * Test constructor throws for negative infinity.
     */
    public function testConstructorThrowsForNegativeInfinity(): void
    {
        $this->expectException(ValueError::class);

        new Length(-INF, 'm');
    }

    /**
     * Test constructor throws for NAN.
     */
    public function testConstructorThrowsForNan(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('cannot be ±INF or NAN');

        new Length(NAN, 'm');
    }

    /**
     * Test constructor throws for invalid unit.
     */
    public function testConstructorThrowsForInvalidUnit(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage("Invalid unit 'invalid'");

        new Length(100, 'invalid');
    }

    /**
     * Test getUnitConverter throws LogicException when not properly configured.
     */
    public function testGetUnitConverterThrowsWhenNotConfigured(): void
    {
        $this->expectException(LogicException::class);
        Coolness::getUnitConverter();
    }

    // endregion

    // region Factory method tests (parse, tryParse)

    /**
     * Test parse with valid input.
     */
    public function testParseValid(): void
    {
        $length = Length::parse('100 m');

        $this->assertSame(100.0, $length->value);
        $this->assertSame('m', $length->unit);
    }

    /**
     * Test parse with no space between value and unit.
     */
    public function testParseNoSpace(): void
    {
        $length = Length::parse('50km');

        $this->assertSame(50.0, $length->value);
        $this->assertSame('km', $length->unit);
    }

    /**
     * Test parse with scientific notation.
     */
    public function testParseScientificNotation(): void
    {
        $length = Length::parse('1.5e3 m');

        $this->assertSame(1500.0, $length->value);
    }

    /**
     * Test parse with negative value.
     */
    public function testParseNegative(): void
    {
        $length = Length::parse('-25.5 cm');

        $this->assertSame(-25.5, $length->value);
        $this->assertSame('cm', $length->unit);
    }

    /**
     * Test parse with leading/trailing whitespace.
     */
    public function testParseWithWhitespace(): void
    {
        $length = Length::parse('  100 m  ');

        $this->assertSame(100.0, $length->value);
    }

    /**
     * Test parse throws for empty string.
     */
    public function testParseThrowsForEmptyString(): void
    {
        $this->expectException(ValueError::class);

        Length::parse('');
    }

    /**
     * Test parse throws for invalid format.
     */
    public function testParseThrowsForInvalidFormat(): void
    {
        $this->expectException(ValueError::class);

        Length::parse('not a measurement');
    }

    /**
     * Test parse throws for invalid unit.
     */
    public function testParseThrowsForInvalidUnit(): void
    {
        $this->expectException(ValueError::class);

        Length::parse('100 bananas');
    }

    /**
     * Test tryParse returns value on success.
     */
    public function testTryParseSuccess(): void
    {
        $length = Length::tryParse('100 m');

        $this->assertNotNull($length);
        $this->assertSame(100.0, $length->value);
    }

    /**
     * Test tryParse returns null on failure.
     */
    public function testTryParseReturnsNullOnFailure(): void
    {
        $result = Length::tryParse('invalid');

        $this->assertNull($result);
    }

    /**
     * Test tryParse returns null for empty string.
     */
    public function testTryParseReturnsNullForEmptyString(): void
    {
        $result = Length::tryParse('');

        $this->assertNull($result);
    }

    // endregion

    // region Conversion tests (to)

    /**
     * Test to() converts between units.
     */
    public function testToConvertsBetweenUnits(): void
    {
        $length = new Length(1, 'km');
        $inMetres = $length->to('m');

        $this->assertSame('m', $inMetres->unit);
        $this->assertEqualsWithDelta(1000.0, $inMetres->value, 1e-10);
    }

    /**
     * Test to() with same unit returns equivalent value.
     */
    public function testToSameUnit(): void
    {
        $length = new Length(100, 'm');
        $result = $length->to('m');

        $this->assertSame(100.0, $result->value);
        $this->assertSame('m', $result->unit);
    }

    /**
     * Test to() returns new instance.
     */
    public function testToReturnsNewInstance(): void
    {
        $length = new Length(100, 'm');
        $result = $length->to('km');

        $this->assertNotSame($length, $result);
    }

    /**
     * Test to() throws for invalid unit.
     */
    public function testToThrowsForInvalidUnit(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);

        $length->to('invalid');
    }

    // endregion

    // region Formatting tests (format, __toString)

    /**
     * Test format with fixed notation.
     */
    public function testFormatFixed(): void
    {
        $length = new Length(123.456, 'm');

        $this->assertSame('123.46m', $length->format('f', 2));
    }

    /**
     * Test format with scientific notation.
     */
    public function testFormatScientific(): void
    {
        $length = new Length(1500, 'm');

        $this->assertSame('1.5e+3m', $length->format('e', 1));
    }

    /**
     * Test format with general notation.
     */
    public function testFormatGeneral(): void
    {
        $length = new Length(1500, 'm');

        $this->assertSame('1500m', $length->format('g', 4));
    }

    /**
     * Test format with trimZeros disabled.
     */
    public function testFormatNoTrimZeros(): void
    {
        $length = new Length(10, 'm');

        $this->assertSame('10.00m', $length->format('f', 2, false));
    }

    /**
     * Test format with includeSpace enabled.
     */
    public function testFormatWithSpace(): void
    {
        $length = new Length(100, 'm');

        $this->assertSame('100 m', $length->format('f', 0, true, true));
    }

    /**
     * Test format throws for invalid specifier.
     */
    public function testFormatThrowsForInvalidSpecifier(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);

        $length->format('x');
    }

    /**
     * Test format throws for negative precision.
     */
    public function testFormatThrowsForNegativePrecision(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);

        $length->format('f', -1);
    }

    /**
     * Test __toString returns formatted string.
     */
    public function testToString(): void
    {
        $length = new Length(100, 'm');

        // __toString has no space between value and unit
        $this->assertSame('100m', (string)$length);
    }

    /**
     * Test __toString with decimal value.
     */
    public function testToStringWithDecimal(): void
    {
        $length = new Length(123.456, 'km');

        $this->assertSame('123.456km', (string)$length);
    }

    /**
     * Test __toString normalizes negative zero.
     */
    public function testToStringNormalizesNegativeZero(): void
    {
        $length = new Length(-0.0, 'm');

        $this->assertSame('0m', (string)$length);
    }

    /**
     * Test formatValue throws on non-finite value.
     */
    public function testFormatValueThrowsOnNonFinite(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('must be finite');

        $method = new ReflectionMethod(Length::class, 'formatValue');
        $method->invoke(null, INF);
    }

    // endregion

    // region Arithmetic tests (add, sub, neg, mul, div, abs)

    /**
     * Test add with Measurement argument.
     */
    public function testAddWithMeasurement(): void
    {
        $a = new Length(100, 'm');
        $b = new Length(50, 'm');

        $result = $a->add($b);

        $this->assertSame(150.0, $result->value);
        $this->assertSame('m', $result->unit);
    }

    /**
     * Test add with value and unit arguments.
     */
    public function testAddWithValueAndUnit(): void
    {
        $a = new Length(100, 'm');

        $result = $a->add(50, 'cm');

        $this->assertEqualsWithDelta(100.5, $result->value, 1e-10);
        $this->assertSame('m', $result->unit);
    }

    /**
     * Test add converts units automatically.
     */
    public function testAddConvertsUnits(): void
    {
        $a = new Length(1, 'm');
        $b = new Length(1, 'km');

        $result = $a->add($b);

        $this->assertEqualsWithDelta(1001.0, $result->value, 1e-10);
        $this->assertSame('m', $result->unit);
    }

    /**
     * Test add returns new instance.
     */
    public function testAddReturnsNewInstance(): void
    {
        $a = new Length(100, 'm');
        $b = new Length(50, 'm');

        $result = $a->add($b);

        $this->assertNotSame($a, $result);
        $this->assertNotSame($b, $result);
    }

    /**
     * Test sub with Measurement argument.
     */
    public function testSubWithMeasurement(): void
    {
        $a = new Length(100, 'm');
        $b = new Length(30, 'm');

        $result = $a->sub($b);

        $this->assertSame(70.0, $result->value);
    }

    /**
     * Test sub with value and unit arguments.
     */
    public function testSubWithValueAndUnit(): void
    {
        $a = new Length(100, 'm');

        $result = $a->sub(50, 'cm');

        $this->assertEqualsWithDelta(99.5, $result->value, 1e-10);
    }

    /**
     * Test sub can produce negative result.
     */
    public function testSubNegativeResult(): void
    {
        $a = new Length(10, 'm');
        $b = new Length(50, 'm');

        $result = $a->sub($b);

        $this->assertSame(-40.0, $result->value);
    }

    /**
     * Test add throws TypeError for value without unit.
     */
    public function testAddThrowsForValueWithoutUnit(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Invalid argument types');

        $length = new Length(100, 'm');
        $length->add(50);
    }

    /**
     * Test sub throws TypeError for value without unit.
     */
    public function testSubThrowsForValueWithoutUnit(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Invalid argument types');

        $length = new Length(100, 'm');
        $length->sub(50);
    }

    /**
     * Test add throws TypeError for wrong Measurement type.
     */
    public function testAddThrowsForWrongMeasurementType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Invalid argument types');

        $length = new Length(100, 'm');
        $area = new Area(50, 'm2');
        $length->add($area);
    }

    /**
     * Test neg negates value.
     */
    public function testNeg(): void
    {
        $length = new Length(100, 'm');

        $result = $length->neg();

        $this->assertSame(-100.0, $result->value);
        $this->assertSame('m', $result->unit);
    }

    /**
     * Test neg on negative value produces positive.
     */
    public function testNegOnNegative(): void
    {
        $length = new Length(-50, 'm');

        $result = $length->neg();

        $this->assertSame(50.0, $result->value);
    }

    /**
     * Test neg returns new instance.
     */
    public function testNegReturnsNewInstance(): void
    {
        $length = new Length(100, 'm');

        $result = $length->neg();

        $this->assertNotSame($length, $result);
    }

    /**
     * Test mul multiplies by scalar.
     */
    public function testMul(): void
    {
        $length = new Length(10, 'm');

        $result = $length->mul(5);

        $this->assertSame(50.0, $result->value);
        $this->assertSame('m', $result->unit);
    }

    /**
     * Test mul with fractional scalar.
     */
    public function testMulFractional(): void
    {
        $length = new Length(100, 'm');

        $result = $length->mul(0.5);

        $this->assertSame(50.0, $result->value);
    }

    /**
     * Test mul with negative scalar.
     */
    public function testMulNegative(): void
    {
        $length = new Length(10, 'm');

        $result = $length->mul(-3);

        $this->assertSame(-30.0, $result->value);
    }

    /**
     * Test mul throws for infinity.
     */
    public function testMulThrowsForInfinity(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);

        $length->mul(INF);
    }

    /**
     * Test mul throws for NAN.
     */
    public function testMulThrowsForNan(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);

        $length->mul(NAN);
    }

    /**
     * Test div divides by scalar.
     */
    public function testDiv(): void
    {
        $length = new Length(100, 'm');

        $result = $length->div(4);

        $this->assertSame(25.0, $result->value);
        $this->assertSame('m', $result->unit);
    }

    /**
     * Test div with fractional scalar.
     */
    public function testDivFractional(): void
    {
        $length = new Length(100, 'm');

        $result = $length->div(0.5);

        $this->assertSame(200.0, $result->value);
    }

    /**
     * Test div throws for zero.
     */
    public function testDivThrowsForZero(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(DivisionByZeroError::class);

        $length->div(0);
    }

    /**
     * Test div throws for infinity.
     */
    public function testDivThrowsForInfinity(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);

        $length->div(INF);
    }

    /**
     * Test div throws for NAN.
     */
    public function testDivThrowsForNan(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);

        $length->div(NAN);
    }

    /**
     * Test abs on positive value.
     */
    public function testAbsPositive(): void
    {
        $length = new Length(100, 'm');

        $result = $length->abs();

        $this->assertSame(100.0, $result->value);
    }

    /**
     * Test abs on negative value.
     */
    public function testAbsNegative(): void
    {
        $length = new Length(-100, 'm');

        $result = $length->abs();

        $this->assertSame(100.0, $result->value);
    }

    /**
     * Test abs on zero.
     */
    public function testAbsZero(): void
    {
        $length = new Length(0, 'm');

        $result = $length->abs();

        $this->assertSame(0.0, $result->value);
    }

    /**
     * Test abs returns new instance.
     */
    public function testAbsReturnsNewInstance(): void
    {
        $length = new Length(-100, 'm');

        $result = $length->abs();

        $this->assertNotSame($length, $result);
    }

    // endregion

    // region Comparison tests (compare, approxEqual)

    /**
     * Test compare returns -1 when less than.
     */
    public function testCompareLessThan(): void
    {
        $a = new Length(10, 'm');
        $b = new Length(20, 'm');

        $this->assertSame(-1, $a->compare($b));
    }

    /**
     * Test compare returns 0 when equal.
     */
    public function testCompareEqual(): void
    {
        $a = new Length(10, 'm');
        $b = new Length(10, 'm');

        $this->assertSame(0, $a->compare($b));
    }

    /**
     * Test compare returns 1 when greater than.
     */
    public function testCompareGreaterThan(): void
    {
        $a = new Length(20, 'm');
        $b = new Length(10, 'm');

        $this->assertSame(1, $a->compare($b));
    }

    /**
     * Test compare converts units automatically.
     */
    public function testCompareConvertsUnits(): void
    {
        $a = new Length(1, 'km');
        $b = new Length(1000, 'm');

        $this->assertSame(0, $a->compare($b));
    }

    /**
     * Test compare throws for different Measurement types.
     */
    public function testCompareThrowsForDifferentTypes(): void
    {
        $length = new Length(100, 'm');
        $area = new Area(100, 'm2');

        $this->expectException(TypeError::class);

        $length->compare($area);
    }

    /**
     * Test compare throws for non-Measurement.
     */
    public function testCompareThrowsForNonMeasurement(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(TypeError::class);

        $length->compare(100);
    }

    /**
     * Test approxEqual returns true for equal values.
     */
    public function testApproxEqualTrue(): void
    {
        $a = new Length(100, 'm');
        $b = new Length(100, 'm');

        $this->assertTrue($a->approxEqual($b));
    }

    /**
     * Test approxEqual returns true within tolerance.
     */
    public function testApproxEqualWithinTolerance(): void
    {
        $a = new Length(100, 'm');
        $b = new Length(100.0000001, 'm');

        $this->assertTrue($a->approxEqual($b));
    }

    /**
     * Test approxEqual returns false for different values.
     */
    public function testApproxEqualFalse(): void
    {
        $a = new Length(100, 'm');
        $b = new Length(200, 'm');

        $this->assertFalse($a->approxEqual($b));
    }

    /**
     * Test approxEqual converts units automatically.
     */
    public function testApproxEqualConvertsUnits(): void
    {
        $a = new Length(1, 'km');
        $b = new Length(1000, 'm');

        $this->assertTrue($a->approxEqual($b));
    }

    /**
     * Test approxEqual returns false for different Measurement types.
     */
    public function testApproxEqualDifferentTypes(): void
    {
        $length = new Length(100, 'm');
        $area = new Area(100, 'm2');

        $this->assertFalse($length->approxEqual($area));
    }

    /**
     * Test approxEqual returns false for non-Measurement.
     */
    public function testApproxEqualNonMeasurement(): void
    {
        $length = new Length(100, 'm');

        $this->assertFalse($length->approxEqual(100));
        $this->assertFalse($length->approxEqual('100m'));
        $this->assertFalse($length->approxEqual(new stdClass()));
    }

    // endregion

    // region formatUnit() tests

    /**
     * Test formatUnit with basic unit (no prefix, no exponent).
     */
    public function testFormatUnitBasic(): void
    {
        $this->assertSame('m', Length::formatUnit('m'));
        $this->assertSame('ft', Length::formatUnit('ft'));
        $this->assertSame('in', Length::formatUnit('in'));
    }

    /**
     * Test formatUnit converts 'u' prefix to 'μ'.
     */
    public function testFormatUnitConvertsMicroPrefix(): void
    {
        $this->assertSame('μm', Length::formatUnit('um'));
    }

    /**
     * Test formatUnit preserves other prefixes.
     */
    public function testFormatUnitPreservesOtherPrefixes(): void
    {
        $this->assertSame('km', Length::formatUnit('km'));
        $this->assertSame('cm', Length::formatUnit('cm'));
        $this->assertSame('mm', Length::formatUnit('mm'));
        $this->assertSame('nm', Length::formatUnit('nm'));
    }

    /**
     * Test formatUnit converts exponent to superscript.
     */
    public function testFormatUnitConvertExponentToSuperscript(): void
    {
        $this->assertSame('m²', Area::formatUnit('m2'));
        $this->assertSame('ft²', Area::formatUnit('ft2'));
        $this->assertSame('in²', Area::formatUnit('in2'));
    }

    /**
     * Test formatUnit handles prefix and exponent together.
     */
    public function testFormatUnitWithPrefixAndExponent(): void
    {
        $this->assertSame('km²', Area::formatUnit('km2'));
        $this->assertSame('cm²', Area::formatUnit('cm2'));
        $this->assertSame('mm²', Area::formatUnit('mm2'));
    }

    /**
     * Test formatUnit converts micro prefix with exponent.
     */
    public function testFormatUnitMicroPrefixWithExponent(): void
    {
        $this->assertSame('μm²', Area::formatUnit('um2'));
    }

    // endregion

    // region Parts methods tests

    /**
     * Test getPartUnits returns empty array in base implementation.
     */
    public function testGetPartUnitsReturnsEmptyByDefault(): void
    {
        // Length doesn't override getPartUnits(), so it returns empty array.
        $this->assertSame([], Length::getPartUnits());
    }

    /**
     * Test validateSmallestUnit throws for invalid unit.
     */
    public function testValidateSmallestUnitThrowsForInvalidUnit(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid smallest unit');

        // Use reflection to call protected method.
        $method = new ReflectionMethod(Length::class, 'validateSmallestUnit');
        $method->invoke(null, 'invalid');
    }

    /**
     * Test validatePrecision accepts null.
     */
    public function testValidatePrecisionAcceptsNull(): void
    {
        $method = new ReflectionMethod(Length::class, 'validatePrecision');

        // Should not throw.
        $method->invoke(null, null);

        $this->assertTrue(true);
    }

    /**
     * Test validatePrecision accepts zero.
     */
    public function testValidatePrecisionAcceptsZero(): void
    {
        $method = new ReflectionMethod(Length::class, 'validatePrecision');

        // Should not throw.
        $method->invoke(null, 0);

        $this->assertTrue(true);
    }

    /**
     * Test validatePrecision accepts positive integer.
     */
    public function testValidatePrecisionAcceptsPositive(): void
    {
        $method = new ReflectionMethod(Length::class, 'validatePrecision');

        // Should not throw.
        $method->invoke(null, 5);

        $this->assertTrue(true);
    }

    /**
     * Test validatePrecision throws for negative value.
     */
    public function testValidatePrecisionThrowsForNegative(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid precision');

        $method = new ReflectionMethod(Length::class, 'validatePrecision');
        $method->invoke(null, -1);
    }

    /**
     * Test validatePartUnits throws when getPartUnits returns empty.
     */
    public function testValidatePartUnitsThrowsWhenEmpty(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must define the part units');

        $method = new ReflectionMethod(Length::class, 'validatePartUnits');
        $method->invoke(null);
    }

    /**
     * Test fromPartsArray throws when getPartUnits returns empty.
     */
    public function testFromPartsArrayThrowsWhenPartUnitsEmpty(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must define the part units');

        Length::fromPartsArray(['m' => 100]);
    }

    /**
     * Test toParts throws when getPartUnits returns empty.
     */
    public function testToPartsThrowsWhenPartUnitsEmpty(): void
    {
        $length = new Length(100, 'm');

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid smallest unit');

        $length->toParts('m');
    }

    /**
     * Test validatePartUnits throws when a part unit is invalid.
     */
    public function testValidatePartUnitsThrowsForInvalidPartUnit(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invalid unit: 'invalid'");

        Badness::fromPartsArray(['foo' => 10]);
    }

    // endregion
}

class Coolness extends Measurement
{
    /** @return array<string, int> */
    public static function getUnits(): array
    {
        return [];
    }

    /** @return array<array{0: string, 1: string, 2: int|float, 3?: int|float}> */
    public static function getConversions(): array
    {
        return [];
    }
}

class Badness extends Measurement
{
    /** @return array<string, int> */
    public static function getUnits(): array
    {
        return [
            'foo' => 0,
            'bar' => 0,
        ];
    }

    /** @return array<array{0: string, 1: string, 2: int|float, 3?: int|float}> */
    public static function getConversions(): array
    {
        return [
            ['foo', 'bar', 10],
        ];
    }

    /** @return string[] */
    public static function getPartUnits(): array
    {
        return ['foo', 'invalid'];
    }
}
