<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests;

use Galaxon\Units\Conversion;
use Galaxon\Units\Measurement;
use Galaxon\Units\UnitConverter;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Tests for UnitConverter class.
 */
#[CoversClass(UnitConverter::class)]
class UnitConverterTest extends TestCase
{
    // region Test fixtures

    /**
     * Create a simple unit converter for testing.
     *
     * @return UnitConverter
     */
    private function createSimpleConverter(): UnitConverter
    {
        return new UnitConverter(
            [
                'm'  => Measurement::PREFIX_CODE_METRIC,
                'ft' => 0,
                'in' => 0,
            ],
            [
                ['m', 'ft', 3.28084],
                ['ft', 'in', 12],
            ]
        );
    }

    /**
     * Create a converter with temperature-style offset conversions.
     *
     * @return UnitConverter
     */
    private function createTemperatureConverter(): UnitConverter
    {
        return new UnitConverter(
            [
                'C' => 0,
                'F' => 0,
                'K' => 0,
            ],
            [
                ['C', 'F', 1.8, 32],
                ['C', 'K', 1, 273.15],
            ]
        );
    }

    // endregion

    // region Constructor tests

    /**
     * Test constructor with valid configuration.
     */
    public function testConstructorWithValidConfiguration(): void
    {
        $converter = $this->createSimpleConverter();

        $this->assertInstanceOf(UnitConverter::class, $converter);
    }

    /**
     * Test constructor throws for empty units array.
     */
    public function testConstructorThrowsForEmptyUnits(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Units must be a non-empty array.');

        new UnitConverter([], []);
    }

    /**
     * Test constructor throws for non-string unit key.
     */
    public function testConstructorThrowsForNonStringUnitKey(): void
    {
        $this->expectException(LogicException::class);

        // Force an integer key by using array union.
        $units = [0 => Measurement::PREFIX_CODE_METRIC];

        new UnitConverter($units, []); // @phpstan-ignore argument.type
    }

    /**
     * Test constructor throws for non-integer prefix set code.
     */
    public function testConstructorThrowsForNonIntegerPrefixSetCode(): void
    {
        $this->expectException(LogicException::class);
        new UnitConverter(['m' => 'invalid'], []); // @phpstan-ignore argument.type
    }

    /**
     * Test constructor throws for invalid unit format (no letters).
     */
    public function testConstructorThrowsForInvalidUnitFormat(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid unit');

        // Use a string that won't be converted to int by PHP but fails the regex.
        new UnitConverter(['123abc' => 0], []);
    }

    /**
     * Test constructor throws for unit with exponent of zero.
     */
    public function testConstructorThrowsForExponentZero(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid exponent 0');

        // Note: 'm0' has exponent '0' which is explicitly disallowed.
        new UnitConverter(['abc0' => 0], []);
    }

    /**
     * Test constructor throws for unit with exponent of one (should be omitted).
     */
    public function testConstructorThrowsForExponentOne(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid exponent 1');

        // Note: 'm1' would have exponent '1' but units should omit exponent of 1.
        new UnitConverter(['abc1' => 0], []);
    }

    /**
     * Test constructor throws for unit with exponent out of range (> 9).
     */
    public function testConstructorThrowsForExponentTooLarge(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid exponent 10');

        new UnitConverter(['m10' => 0], []);
    }

    /**
     * Test constructor throws for unit with exponent out of range (< -9).
     */
    public function testConstructorThrowsForExponentTooSmall(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid exponent -10');

        new UnitConverter(['m-10' => 0], []);
    }

    /**
     * Test constructor throws for conversion with wrong number of elements.
     */
    public function testConstructorThrowsForConversionWithWrongElementCount(): void
    {
        $this->expectException(LogicException::class);

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [ // @phpstan-ignore argument.type
                ['m', 'ft'],  // Only 2 elements.
            ]
        );
    }

    /**
     * Test constructor throws for conversion with too many elements.
     */
    public function testConstructorThrowsForConversionWithTooManyElements(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Each conversion must have 3 or 4 elements.');

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [
                ['m', 'ft', 3.28084, 0, 'extra'],  // 5 elements.
            ]
        );
    }

    /**
     * Test constructor throws for non-string initial unit in conversion.
     */
    public function testConstructorThrowsForNonStringInitialUnit(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Initial unit in conversion must be a string.');

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [ // @phpstan-ignore argument.type
                [123, 'ft', 3.28084],
            ]
        );
    }

    /**
     * Test constructor throws for invalid initial unit in conversion.
     */
    public function testConstructorThrowsForInvalidInitialUnit(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Initial unit 'invalid' in conversion is not a valid unit.");

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [
                ['invalid', 'ft', 3.28084],
            ]
        );
    }

    /**
     * Test constructor throws for non-string final unit in conversion.
     */
    public function testConstructorThrowsForNonStringFinalUnit(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Final unit in conversion must be a string.');

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [ // @phpstan-ignore argument.type
                ['m', 456, 3.28084],
            ]
        );
    }

    /**
     * Test constructor throws for invalid final unit in conversion.
     */
    public function testConstructorThrowsForInvalidFinalUnit(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Final unit 'invalid' in conversion is not a valid unit.");

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [
                ['m', 'invalid', 3.28084],
            ]
        );
    }

    /**
     * Test constructor throws for non-numeric multiplier in conversion.
     */
    public function testConstructorThrowsForNonNumericMultiplier(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Multiplier in conversion must be a number');

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [ // @phpstan-ignore argument.type
                ['m', 'ft', 'three'],
            ]
        );
    }

    /**
     * Test constructor throws for zero multiplier in conversion.
     */
    public function testConstructorThrowsForZeroMultiplier(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Multiplier in conversion cannot be zero.');

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [
                ['m', 'ft', 0],
            ]
        );
    }

    /**
     * Test constructor throws for non-numeric offset in conversion.
     */
    public function testConstructorThrowsForNonNumericOffset(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Offset in conversion must be omitted, or a number');

        new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [ // @phpstan-ignore argument.type
                ['m', 'ft', 3.28084, 'zero'],
            ]
        );
    }

    /**
     * Test constructor accepts conversion with prefixed units.
     */
    public function testConstructorAcceptsPrefixedUnitsInConversion(): void
    {
        $converter = new UnitConverter(
            ['m' => Measurement::PREFIX_CODE_METRIC, 'ft' => 0],
            [
                ['km', 'ft', 3280.84],  // Using prefixed unit.
            ]
        );

        $this->assertInstanceOf(UnitConverter::class, $converter);
    }

    // endregion

    // region getValidUnits() tests

    /**
     * Test getValidUnits returns base units.
     */
    public function testGetValidUnitsReturnsBaseUnits(): void
    {
        $converter = new UnitConverter(
            ['m' => 0, 'ft' => 0],
            []
        );

        $validUnits = $converter->getUnitSymbols();

        $this->assertContains('m', $validUnits);
        $this->assertContains('ft', $validUnits);
    }

    /**
     * Test getValidUnits returns prefixed units.
     */
    public function testGetValidUnitsReturnsPrefixedUnits(): void
    {
        $converter = $this->createSimpleConverter();

        $validUnits = $converter->getUnitSymbols();

        $this->assertContains('m', $validUnits);
        $this->assertContains('km', $validUnits);
        $this->assertContains('cm', $validUnits);
        $this->assertContains('mm', $validUnits);
        $this->assertContains('ft', $validUnits);  // No prefix.
        $this->assertNotContains('kft', $validUnits);  // ft doesn't accept prefixes.
    }

    // endregion

    // region getPrefixes() tests

    /**
     * Test getPrefixes returns small metric prefixes.
     */
    public function testGetPrefixesReturnsSmallMetricPrefixes(): void
    {
        $prefixes = UnitConverter::getPrefixes(Measurement::PREFIX_CODE_SMALL_METRIC);

        $this->assertArrayHasKey('m', $prefixes);  // milli
        $this->assertArrayHasKey('μ', $prefixes);  // micro
        $this->assertArrayHasKey('n', $prefixes);  // nano
        $this->assertArrayNotHasKey('k', $prefixes);  // kilo is large metric
        $this->assertArrayNotHasKey('Ki', $prefixes);  // binary
    }

    /**
     * Test getPrefixes returns large metric prefixes.
     */
    public function testGetPrefixesReturnsLargeMetricPrefixes(): void
    {
        $prefixes = UnitConverter::getPrefixes(Measurement::PREFIX_CODE_LARGE_METRIC);

        $this->assertArrayHasKey('k', $prefixes);  // kilo
        $this->assertArrayHasKey('M', $prefixes);  // mega
        $this->assertArrayHasKey('G', $prefixes);  // giga
        $this->assertArrayNotHasKey('m', $prefixes);  // milli is small metric
        $this->assertArrayNotHasKey('Ki', $prefixes);  // binary
    }

    /**
     * Test getPrefixes returns binary prefixes.
     */
    public function testGetPrefixesReturnsBinaryPrefixes(): void
    {
        $prefixes = UnitConverter::getPrefixes(Measurement::PREFIX_CODE_BINARY);

        $this->assertArrayHasKey('Ki', $prefixes);  // kibi
        $this->assertArrayHasKey('Mi', $prefixes);  // mebi
        $this->assertArrayHasKey('Gi', $prefixes);  // gibi
        $this->assertArrayNotHasKey('k', $prefixes);  // kilo is metric
        $this->assertArrayNotHasKey('m', $prefixes);  // milli is metric
    }

    /**
     * Test getPrefixes returns all metric prefixes.
     */
    public function testGetPrefixesReturnsAllMetricPrefixes(): void
    {
        $prefixes = UnitConverter::getPrefixes(Measurement::PREFIX_CODE_METRIC);

        $this->assertArrayHasKey('m', $prefixes);  // milli (small)
        $this->assertArrayHasKey('k', $prefixes);  // kilo (large)
        $this->assertArrayNotHasKey('Ki', $prefixes);  // binary
    }

    /**
     * Test getPrefixes returns all prefixes.
     */
    public function testGetPrefixesReturnsAllPrefixes(): void
    {
        $prefixes = UnitConverter::getPrefixes(Measurement::PREFIX_CODE_ALL);

        $this->assertArrayHasKey('m', $prefixes);  // milli (small metric)
        $this->assertArrayHasKey('k', $prefixes);  // kilo (large metric)
        $this->assertArrayHasKey('Ki', $prefixes);  // kibi (binary)
    }

    /**
     * Test getPrefixes caches results.
     */
    public function testGetPrefixesCachesResults(): void
    {
        $prefixes1 = UnitConverter::getPrefixes(Measurement::PREFIX_CODE_SMALL_METRIC);
        $prefixes2 = UnitConverter::getPrefixes(Measurement::PREFIX_CODE_SMALL_METRIC);

        // Same reference should be returned from cache.
        $this->assertSame($prefixes1, $prefixes2);
    }

    // endregion

    // region getUnit() tests

    /**
     * Test getUnit with base unit (no prefix).
     */
    public function testGetUnitWithBaseUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $oUnit = $converter->getUnit('m');

        $this->assertSame('', $oUnit->prefix);
        $this->assertSame('m', $oUnit->base);
        $this->assertSame(1, $oUnit->exponent);
    }

    /**
     * Test getUnit with prefixed unit.
     */
    public function testGetUnitWithPrefixedUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $oUnit = $converter->getUnit('km');

        $this->assertSame('k', $oUnit->prefix);
        $this->assertSame('m', $oUnit->base);
        $this->assertSame(1, $oUnit->exponent);
    }

    /**
     * Test getUnit with unit that doesn't accept prefixes.
     */
    public function testGetUnitWithNoPrefixUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $oUnit = $converter->getUnit('ft');

        $this->assertSame('', $oUnit->prefix);
        $this->assertSame('ft', $oUnit->base);
        $this->assertSame(1, $oUnit->exponent);
    }

    /**
     * Test getUnit throws for invalid unit.
     */
    public function testGetUnitThrowsForInvalidUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage("Invalid unit 'invalid'");

        $converter->getUnit('invalid');
    }

    /**
     * Test getUnit with micro prefix (u alias).
     */
    public function testGetUnitWithMicroPrefix(): void
    {
        $converter = $this->createSimpleConverter();

        $oUnit = $converter->getUnit('um');

        $this->assertSame('u', $oUnit->prefix);
        $this->assertSame('m', $oUnit->base);
        $this->assertSame(1, $oUnit->exponent);
    }

    /**
     * Test getUnit with positive exponent.
     */
    public function testGetUnitWithPositiveExponent(): void
    {
        $converter = new UnitConverter(
            ['m2' => Measurement::PREFIX_CODE_METRIC],
            []
        );

        $oUnit = $converter->getUnit('m2');

        $this->assertSame('', $oUnit->prefix);
        $this->assertSame('m', $oUnit->base);
        $this->assertSame(2, $oUnit->exponent);
    }

    /**
     * Test getUnit with prefixed unit and positive exponent.
     */
    public function testGetUnitWithPrefixAndPositiveExponent(): void
    {
        $converter = new UnitConverter(
            ['m2' => Measurement::PREFIX_CODE_METRIC],
            []
        );

        $oUnit = $converter->getUnit('km2');

        $this->assertSame('k', $oUnit->prefix);
        $this->assertSame('m', $oUnit->base);
        $this->assertSame(2, $oUnit->exponent);
    }

    /**
     * Test getUnit with negative exponent.
     */
    public function testGetUnitWithNegativeExponent(): void
    {
        $converter = new UnitConverter(
            ['s-2' => Measurement::PREFIX_CODE_METRIC],
            []
        );

        $oUnit = $converter->getUnit('s-2');

        $this->assertSame('', $oUnit->prefix);
        $this->assertSame('s', $oUnit->base);
        $this->assertSame(-2, $oUnit->exponent);
    }

    /**
     * Test getUnit with prefixed unit and negative exponent.
     */
    public function testGetUnitWithPrefixAndNegativeExponent(): void
    {
        $converter = new UnitConverter(
            ['s-1' => Measurement::PREFIX_CODE_METRIC],
            []
        );

        $oUnit = $converter->getUnit('ms-1');

        $this->assertSame('m', $oUnit->prefix);
        $this->assertSame('s', $oUnit->base);
        $this->assertSame(-1, $oUnit->exponent);
    }

    // endregion

    // region composeUnit() tests

    /**
     * Test composeUnit with prefix.
     */
    public function testComposeUnitWithPrefix(): void
    {
        $converter = $this->createSimpleConverter();

        $unit = $converter->composeUnitSymbol('k', 'm', 1);

        $this->assertSame('km', $unit);
    }

    /**
     * Test composeUnit without prefix.
     */
    public function testComposeUnitWithoutPrefix(): void
    {
        $converter = $this->createSimpleConverter();

        $unit = $converter->composeUnitSymbol('', 'ft', 1);

        $this->assertSame('ft', $unit);
    }

    /**
     * Test composeUnit with positive exponent.
     */
    public function testComposeUnitWithPositiveExponent(): void
    {
        $converter = $this->createSimpleConverter();

        $unit = $converter->composeUnitSymbol('k', 'm', 2);

        $this->assertSame('km2', $unit);
    }

    /**
     * Test composeUnit with prefix and exponent.
     */
    public function testComposeUnitWithPrefixAndExponent(): void
    {
        $converter = $this->createSimpleConverter();

        $unit = $converter->composeUnitSymbol('c', 'm', 3);

        $this->assertSame('cm3', $unit);
    }

    /**
     * Test composeUnit with negative exponent.
     */
    public function testComposeUnitWithNegativeExponent(): void
    {
        $converter = $this->createSimpleConverter();

        $unit = $converter->composeUnitSymbol('', 's', -2);

        $this->assertSame('s-2', $unit);
    }

    /**
     * Test composeUnit with prefix and negative exponent.
     */
    public function testComposeUnitWithPrefixAndNegativeExponent(): void
    {
        $converter = $this->createSimpleConverter();

        $unit = $converter->composeUnitSymbol('k', 'm', -1);

        $this->assertSame('km-1', $unit);
    }

    // endregion

    // region checkUnitIsValid() tests

    /**
     * Test checkUnitIsValid passes for valid base unit.
     */
    public function testCheckUnitIsValidPassesForBaseUnit(): void
    {
        $this->expectNotToPerformAssertions();

        $converter = $this->createSimpleConverter();
        $converter->checkIsValidUnitSymbol('m');
        $converter->checkIsValidUnitSymbol('ft');
        $converter->checkIsValidUnitSymbol('in');
    }

    /**
     * Test checkUnitIsValid passes for valid prefixed unit.
     */
    public function testCheckUnitIsValidPassesForPrefixedUnit(): void
    {
        $this->expectNotToPerformAssertions();

        $converter = $this->createSimpleConverter();
        $converter->checkIsValidUnitSymbol('km');
        $converter->checkIsValidUnitSymbol('cm');
        $converter->checkIsValidUnitSymbol('mm');
    }

    /**
     * Test checkUnitIsValid throws for invalid unit.
     */
    public function testCheckUnitIsValidThrowsForInvalidUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage("Invalid unit 'xyz'");

        $converter->checkIsValidUnitSymbol('xyz');
    }

    /**
     * Test checkUnitIsValid throws for prefix on non-prefixable unit.
     */
    public function testCheckUnitIsValidThrowsForInvalidPrefixedUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage("Invalid unit 'kft'");

        $converter->checkIsValidUnitSymbol('kft');  // ft doesn't accept prefixes.
    }

    // endregion

    // region getConversion() tests

    /**
     * Test getConversion for same unit returns unity conversion.
     */
    public function testGetConversionForSameUnitReturnsUnity(): void
    {
        $converter = $this->createSimpleConverter();

        $conversion = $converter->getConversion('m', 'm');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame('m', $conversion->initialUnit);
        $this->assertSame('m', $conversion->finalUnit);
        $this->assertSame(1.0, $conversion->multiplier->value);
        $this->assertSame(0.0, $conversion->offset->value);
    }

    /**
     * Test getConversion for direct conversion.
     */
    public function testGetConversionForDirectConversion(): void
    {
        $converter = $this->createSimpleConverter();

        $conversion = $converter->getConversion('m', 'ft');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame('m', $conversion->initialUnit);
        $this->assertSame('ft', $conversion->finalUnit);
        $this->assertEqualsWithDelta(3.28084, $conversion->multiplier->value, 1e-10);
    }

    /**
     * Test getConversion generates inverse conversion.
     */
    public function testGetConversionGeneratesInverseConversion(): void
    {
        $converter = $this->createSimpleConverter();

        $conversion = $converter->getConversion('ft', 'm');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame('ft', $conversion->initialUnit);
        $this->assertSame('m', $conversion->finalUnit);
        $this->assertEqualsWithDelta(1.0 / 3.28084, $conversion->multiplier->value, 1e-10);
    }

    /**
     * Test getConversion finds transitive conversion.
     */
    public function testGetConversionFindsTransitiveConversion(): void
    {
        $converter = $this->createSimpleConverter();

        // m -> in requires m -> ft -> in.
        $conversion = $converter->getConversion('m', 'in');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame('m', $conversion->initialUnit);
        $this->assertSame('in', $conversion->finalUnit);
        // 3.28084 * 12 = 39.37008
        $this->assertEqualsWithDelta(3.28084 * 12, $conversion->multiplier->value, 1e-5);
    }

    /**
     * Test getConversion handles prefix-only conversion (same base unit).
     */
    public function testGetConversionHandlesPrefixOnlyConversion(): void
    {
        $converter = $this->createSimpleConverter();

        $conversion = $converter->getConversion('km', 'm');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame('km', $conversion->initialUnit);
        $this->assertSame('m', $conversion->finalUnit);
        $this->assertEqualsWithDelta(1000.0, $conversion->multiplier->value, 1e-10);
    }

    /**
     * Test getConversion handles conversion between different prefixed units.
     */
    public function testGetConversionBetweenDifferentPrefixes(): void
    {
        $converter = $this->createSimpleConverter();

        $conversion = $converter->getConversion('km', 'cm');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame('km', $conversion->initialUnit);
        $this->assertSame('cm', $conversion->finalUnit);
        $this->assertEqualsWithDelta(100000.0, $conversion->multiplier->value, 1e-10);
    }

    /**
     * Test getConversion handles prefixed to non-prefixed conversion.
     */
    public function testGetConversionFromPrefixedToNonPrefixed(): void
    {
        $converter = $this->createSimpleConverter();

        $conversion = $converter->getConversion('km', 'ft');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame('km', $conversion->initialUnit);
        $this->assertSame('ft', $conversion->finalUnit);
        $this->assertEqualsWithDelta(3280.84, $conversion->multiplier->value, 1e-2);
    }

    /**
     * Test getConversion caches generated conversions.
     */
    public function testGetConversionCachesResult(): void
    {
        $converter = $this->createSimpleConverter();

        $conversion1 = $converter->getConversion('km', 'ft');
        $conversion2 = $converter->getConversion('km', 'ft');

        $this->assertSame($conversion1, $conversion2);
    }

    /**
     * Test getConversion throws for invalid initial unit.
     */
    public function testGetConversionThrowsForInvalidInitialUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $this->expectException(ValueError::class);

        $converter->getConversion('invalid', 'm');
    }

    /**
     * Test getConversion throws for invalid final unit.
     */
    public function testGetConversionThrowsForInvalidFinalUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $this->expectException(ValueError::class);

        $converter->getConversion('m', 'invalid');
    }

    /**
     * Test getConversion throws when no path exists.
     */
    public function testGetConversionThrowsWhenNoPathExists(): void
    {
        // Create converter with disconnected units.
        $converter = new UnitConverter(
            ['m' => 0, 'kg' => 0],
            []  // No conversions defined.
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("No conversion between 'm' and 'kg' could be found.");

        $converter->getConversion('m', 'kg');
    }

    // endregion

    // region getConversion() with offset tests

    /**
     * Test getConversion with offset (temperature-style).
     */
    public function testGetConversionWithOffset(): void
    {
        $converter = $this->createTemperatureConverter();

        $conversion = $converter->getConversion('C', 'F');

        $this->assertInstanceOf(Conversion::class, $conversion);
        $this->assertSame(1.8, $conversion->multiplier->value);
        $this->assertSame(32.0, $conversion->offset->value);
    }

    /**
     * Test getConversion generates inverse with offset.
     */
    public function testGetConversionGeneratesInverseWithOffset(): void
    {
        $converter = $this->createTemperatureConverter();

        $conversion = $converter->getConversion('F', 'C');

        $this->assertInstanceOf(Conversion::class, $conversion);
        // F -> C: C = (F - 32) / 1.8
        $this->assertEqualsWithDelta(1.0 / 1.8, $conversion->multiplier->value, 1e-10);
        $this->assertEqualsWithDelta(-32.0 / 1.8, $conversion->offset->value, 1e-10);
    }

    /**
     * Test getConversion finds transitive path with offsets.
     */
    public function testGetConversionFindsTransitivePathWithOffsets(): void
    {
        $converter = $this->createTemperatureConverter();

        // F -> K requires F -> C -> K.
        $conversion = $converter->getConversion('F', 'K');

        $this->assertInstanceOf(Conversion::class, $conversion);

        // Verify by applying: 32F should be 273.15K (0C).
        $result = $conversion->apply(32.0);
        $this->assertEqualsWithDelta(273.15, $result->value, 1e-10);

        // 212F should be 373.15K (100C).
        $result = $conversion->apply(212.0);
        $this->assertEqualsWithDelta(373.15, $result->value, 1e-10);
    }

    // endregion

    // region convert() tests

    /**
     * Test convert with simple conversion.
     */
    public function testConvertSimple(): void
    {
        $converter = $this->createSimpleConverter();

        $result = $converter->convert(1.0, 'm', 'ft');

        $this->assertEqualsWithDelta(3.28084, $result, 1e-5);
    }

    /**
     * Test convert with prefix conversion.
     */
    public function testConvertWithPrefix(): void
    {
        $converter = $this->createSimpleConverter();

        $result = $converter->convert(1.0, 'km', 'm');

        $this->assertEqualsWithDelta(1000.0, $result, 1e-10);
    }

    /**
     * Test convert with offset.
     */
    public function testConvertWithOffset(): void
    {
        $converter = $this->createTemperatureConverter();

        // 0C = 32F.
        $this->assertEqualsWithDelta(32.0, $converter->convert(0.0, 'C', 'F'), 1e-10);

        // 100C = 212F.
        $this->assertEqualsWithDelta(212.0, $converter->convert(100.0, 'C', 'F'), 1e-10);

        // -40C = -40F.
        $this->assertEqualsWithDelta(-40.0, $converter->convert(-40.0, 'C', 'F'), 1e-10);
    }

    /**
     * Test convert throws for invalid unit.
     */
    public function testConvertThrowsForInvalidUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $this->expectException(ValueError::class);

        $converter->convert(1.0, 'invalid', 'm');
    }

    // endregion

    // region Dynamic modification tests - addBaseUnit()

    /**
     * Test addBaseUnit adds new unit.
     */
    public function testAddUnitAddsNewUnit(): void
    {
        $converter = $this->createSimpleConverter();

        $converter->addUnit('yd', 0);

        $this->assertContains('yd', $converter->getUnitSymbols());
    }

    /**
     * Test addBaseUnit with prefix support.
     */
    public function testAddUnitWithPrefixSupport(): void
    {
        $converter = $this->createSimpleConverter();

        $converter->addUnit('g', Measurement::PREFIX_CODE_METRIC);

        $validUnits = $converter->getUnitSymbols();
        $this->assertContains('g', $validUnits);
        $this->assertContains('kg', $validUnits);
        $this->assertContains('mg', $validUnits);
    }

    /**
     * Test addBaseUnit updates existing unit.
     */
    public function testAddUnitUpdatesExistingUnit(): void
    {
        $converter = new UnitConverter(
            ['m' => 0],  // No prefixes initially.
            []
        );

        $this->assertNotContains('km', $converter->getUnitSymbols());

        $converter->addUnit('m', Measurement::PREFIX_CODE_METRIC);

        $this->assertContains('km', $converter->getUnitSymbols());
    }

    // endregion

    // region Dynamic modification tests - removeBaseUnit()

    /**
     * Test removeBaseUnit removes unit.
     */
    public function testRemoveUnitRemovesUnit(): void
    {
        // Create a converter without conversions referencing the unit we'll remove.
        $converter = new UnitConverter(
            [
                'm'  => Measurement::PREFIX_CODE_METRIC,
                'ft' => 0,
                'yd' => 0,
            ],
            [
                ['m', 'ft', 3.28084],
                // yd has no conversions, so it can be safely removed.
            ]
        );

        $converter->removeUnit('yd');

        $this->assertNotContains('yd', $converter->getUnitSymbols());
    }

    /**
     * Test removeBaseUnit removes prefixed variants.
     */
    public function testRemoveUnitRemovesPrefixedVariants(): void
    {
        // Create a converter where 'g' (gram) can be removed without affecting conversions.
        $converter = new UnitConverter(
            [
                'm' => Measurement::PREFIX_CODE_METRIC,
                'g' => Measurement::PREFIX_CODE_METRIC,
            ],
            []  // No conversions, so removing either unit is safe.
        );

        $this->assertContains('kg', $converter->getUnitSymbols());
        $this->assertContains('mg', $converter->getUnitSymbols());

        $converter->removeUnit('g');

        $this->assertNotContains('g', $converter->getUnitSymbols());
        $this->assertNotContains('kg', $converter->getUnitSymbols());
        $this->assertNotContains('mg', $converter->getUnitSymbols());
        // m should still exist.
        $this->assertContains('m', $converter->getUnitSymbols());
        $this->assertContains('km', $converter->getUnitSymbols());
    }

    // endregion

    // region Dynamic modification tests - addConversion()

    /**
     * Test addConversion adds new conversion.
     */
    public function testAddConversionAddsNewConversion(): void
    {
        $converter = new UnitConverter(
            ['m' => 0, 'ft' => 0, 'yd' => 0],
            [
                ['m', 'ft', 3.28084],
            ]
        );

        $converter->addConversion('ft', 'yd', 1.0 / 3.0);

        // Should now be able to convert m -> yd.
        $conversion = $converter->getConversion('m', 'yd');
        $this->assertInstanceOf(Conversion::class, $conversion);
    }

    /**
     * Test addConversion updates existing conversion.
     */
    public function testAddConversionUpdatesExistingConversion(): void
    {
        $converter = new UnitConverter(
            ['m' => 0, 'ft' => 0],
            [
                ['m', 'ft', 3.0],  // Incorrect value.
            ]
        );

        $converter->addConversion('m', 'ft', 3.28084);  // Correct value.

        $result = $converter->convert(1.0, 'm', 'ft');
        $this->assertEqualsWithDelta(3.28084, $result, 1e-5);
    }

    /**
     * Test addConversion with offset.
     */
    public function testAddConversionWithOffset(): void
    {
        $converter = new UnitConverter(
            ['C' => 0, 'F' => 0],
            []
        );

        $converter->addConversion('C', 'F', 1.8, 32);

        $result = $converter->convert(100.0, 'C', 'F');
        $this->assertEqualsWithDelta(212.0, $result, 1e-10);
    }

    /**
     * Test addConversion throws for zero multiplier.
     */
    public function testAddConversionThrowsForZeroMultiplier(): void
    {
        $converter = new UnitConverter(
            ['m' => 0, 'ft' => 0],
            []
        );

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Multiplier cannot be zero');

        $converter->addConversion('m', 'ft', 0);
    }

    // endregion

    // region Dynamic modification tests - removeConversion()

    /**
     * Test removeConversion removes conversion.
     */
    public function testRemoveConversionRemovesConversion(): void
    {
        $converter = new UnitConverter(
            ['m' => 0, 'ft' => 0, 'in' => 0],
            [
                ['m', 'ft', 3.28084],
                ['ft', 'in', 12],
            ]
        );

        // Remove the bridge conversion.
        $converter->removeConversion('m', 'ft');

        // Now m and ft/in are disconnected.
        $this->expectException(LogicException::class);
        $converter->getConversion('m', 'ft');
    }

    // endregion

    // region Integration tests

    /**
     * Test full conversion chain with multiple units and prefixes.
     */
    public function testFullConversionChain(): void
    {
        $converter = $this->createSimpleConverter();

        // Convert 1 km to inches.
        $result = $converter->convert(1.0, 'km', 'in');

        // 1 km = 1000 m = 1000 * 3.28084 ft = 3280.84 * 12 in = 39370.08 in.
        $this->assertEqualsWithDelta(39370.08, $result, 0.1);
    }

    /**
     * Test round-trip conversion preserves value.
     */
    public function testRoundTripConversionPreservesValue(): void
    {
        $converter = $this->createSimpleConverter();

        $original = 123.456;
        $converted = $converter->convert($original, 'm', 'ft');
        $roundTrip = $converter->convert($converted, 'ft', 'm');

        $this->assertEqualsWithDelta($original, $roundTrip, 1e-10);
    }

    /**
     * Test round-trip with temperature offset.
     */
    public function testRoundTripWithTemperatureOffset(): void
    {
        $converter = $this->createTemperatureConverter();

        $original = 25.0;  // 25C.
        $fahrenheit = $converter->convert($original, 'C', 'F');
        $roundTrip = $converter->convert($fahrenheit, 'F', 'C');

        $this->assertEqualsWithDelta($original, $roundTrip, 1e-10);
    }

    /**
     * Test complex temperature conversion chain.
     */
    public function testComplexTemperatureConversionChain(): void
    {
        $converter = $this->createTemperatureConverter();

        // 0K = -273.15C = -459.67F.
        $kelvinToFahrenheit = $converter->convert(0.0, 'K', 'F');
        $this->assertEqualsWithDelta(-459.67, $kelvinToFahrenheit, 0.01);

        // 373.15K = 100C = 212F.
        $kelvinToFahrenheit = $converter->convert(373.15, 'K', 'F');
        $this->assertEqualsWithDelta(212.0, $kelvinToFahrenheit, 0.01);
    }

    // endregion
}
