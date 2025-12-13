<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests;

use Galaxon\Units\Conversion;
use Galaxon\Units\FloatWithError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Tests for Conversion class.
 */
#[CoversClass(Conversion::class)]
class ConversionTest extends TestCase
{
    // region Constructor tests

    /**
     * Test constructor with basic parameters.
     */
    public function testConstructorBasic(): void
    {
        $conv = new Conversion('m', 'km', 0.001);

        $this->assertSame('m', $conv->initialUnit);
        $this->assertSame('km', $conv->finalUnit);
        $this->assertSame(0.001, $conv->multiplier->value);
        $this->assertSame(0.0, $conv->offset->value);
    }

    /**
     * Test constructor with offset.
     */
    public function testConstructorWithOffset(): void
    {
        $conv = new Conversion('C', 'F', 1.8, 32.0);

        $this->assertSame('C', $conv->initialUnit);
        $this->assertSame('F', $conv->finalUnit);
        $this->assertSame(1.8, $conv->multiplier->value);
        $this->assertSame(32.0, $conv->offset->value);
    }

    /**
     * Test constructor with FloatWithError parameters.
     */
    public function testConstructorWithFloatWithError(): void
    {
        $multiplier = new FloatWithError(2.0, 0.01);
        $offset = new FloatWithError(10.0, 0.1);

        $conv = new Conversion('a', 'b', $multiplier, $offset);

        $this->assertSame(2.0, $conv->multiplier->value);
        $this->assertSame(0.01, $conv->multiplier->absoluteError);
        $this->assertSame(10.0, $conv->offset->value);
        $this->assertSame(0.1, $conv->offset->absoluteError);
    }

    /**
     * Test constructor with integer parameters.
     */
    public function testConstructorWithIntegers(): void
    {
        $conv = new Conversion('mm', 'cm', 10, 0);

        $this->assertSame(10.0, $conv->multiplier->value);
        $this->assertSame(0.0, $conv->offset->value);
    }

    /**
     * Test constructor throws ValueError for zero multiplier.
     */
    public function testConstructorThrowsForZeroMultiplier(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Multiplier cannot be zero.');
        new Conversion('a', 'b', 0);
    }

    /**
     * Test constructor throws ValueError for FloatWithError zero multiplier.
     */
    public function testConstructorThrowsForZeroFloatWithErrorMultiplier(): void
    {
        $multiplier = new FloatWithError(0.0, 0.0);

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Multiplier cannot be zero.');
        new Conversion('a', 'b', $multiplier);
    }

    // endregion

    // region Property tests

    /**
     * Test errorScore property calculation.
     */
    public function testErrorScoreCalculation(): void
    {
        $multiplier = new FloatWithError(2.0, 0.01);
        $offset = new FloatWithError(10.0, 0.1);

        $conv = new Conversion('a', 'b', $multiplier, $offset);

        // Error score = multiplier error + offset error
        $this->assertSame(0.11, $conv->totalAbsoluteError);
    }

    /**
     * Test errorScore with zero errors.
     */
    public function testErrorScoreWithZeroErrors(): void
    {
        $conv = new Conversion('a', 'b', 2, 10);

        // Both are exact integers, so zero error
        $this->assertSame(0.0, $conv->totalAbsoluteError);
    }

    // endregion

    // region apply() tests

    /**
     * Test apply with simple multiplier.
     */
    public function testApplySimpleMultiplier(): void
    {
        $conv = new Conversion('m', 'cm', 100);

        $result = $conv->apply(5.0);

        $this->assertInstanceOf(FloatWithError::class, $result);
        $this->assertSame(500.0, $result->value);
    }

    /**
     * Test apply with multiplier and offset.
     */
    public function testApplyWithMultiplierAndOffset(): void
    {
        $conv = new Conversion('C', 'F', 1.8, 32.0);

        // 0°C = 32°F
        $result1 = $conv->apply(0.0);
        $this->assertInstanceOf(FloatWithError::class, $result1);
        $this->assertSame(32.0, $result1->value);

        // 100°C = 212°F
        $result2 = $conv->apply(100.0);
        $this->assertInstanceOf(FloatWithError::class, $result2);
        $this->assertSame(212.0, $result2->value);

        // -40°C = -40°F
        $result3 = $conv->apply(-40.0);
        $this->assertInstanceOf(FloatWithError::class, $result3);
        $this->assertSame(-40.0, $result3->value);
    }

    /**
     * Test apply with negative multiplier.
     */
    public function testApplyWithNegativeMultiplier(): void
    {
        $conv = new Conversion('a', 'b', -2.0, 10.0);

        $result = $conv->apply(5.0);

        $this->assertInstanceOf(FloatWithError::class, $result);
        $this->assertSame(0.0, $result->value); // 5 * (-2) + 10 = 0
    }

    /**
     * Test apply with fractional multiplier.
     */
    public function testApplyWithFractionalMultiplier(): void
    {
        $conv = new Conversion('km', 'm', 1000);

        $result = $conv->apply(2.5);

        $this->assertInstanceOf(FloatWithError::class, $result);
        $this->assertEqualsWithDelta(2500.0, $result->value, 1e-10);
    }

    /**
     * Test apply with zero input.
     */
    public function testApplyWithZeroInput(): void
    {
        $conv = new Conversion('C', 'F', 1.8, 32.0);

        $result = $conv->apply(0.0);

        $this->assertInstanceOf(FloatWithError::class, $result);
        $this->assertSame(32.0, $result->value);
    }

    /**
     * Test apply accepts int input.
     */
    public function testApplyWithIntInput(): void
    {
        $conv = new Conversion('m', 'cm', 100);

        $result = $conv->apply(5);

        $this->assertInstanceOf(FloatWithError::class, $result);
        $this->assertSame(500.0, $result->value);
    }

    // endregion

    // region invert() tests

    /**
     * Test invert swaps units.
     */
    public function testInvertSwapsUnits(): void
    {
        $conv = new Conversion('m', 'km', 0.001);
        $inverted = $conv->invert();

        $this->assertSame('km', $inverted->initialUnit);
        $this->assertSame('m', $inverted->finalUnit);
    }

    /**
     * Test invert with simple multiplier.
     */
    public function testInvertSimpleMultiplier(): void
    {
        $conv = new Conversion('m', 'km', 0.001);
        $inverted = $conv->invert();

        $this->assertSame(1000.0, $inverted->multiplier->value);
        $this->assertSame(0.0, $inverted->offset->value);
    }

    /**
     * Test invert with multiplier and offset.
     */
    public function testInvertWithMultiplierAndOffset(): void
    {
        // C to F: F = C * 1.8 + 32
        $conv = new Conversion('C', 'F', 1.8, 32.0);
        $inverted = $conv->invert();

        // F to C: C = (F - 32) / 1.8 = F * (1/1.8) + (-32/1.8)
        $this->assertEqualsWithDelta(1.0 / 1.8, $inverted->multiplier->value, 1e-10);
        $this->assertEqualsWithDelta(-32.0 / 1.8, $inverted->offset->value, 1e-10);
    }

    /**
     * Test invert is reversible.
     */
    public function testInvertIsReversible(): void
    {
        $conv = new Conversion('m', 'km', 0.001);
        $doubleInverted = $conv->invert()->invert();

        $this->assertSame('m', $doubleInverted->initialUnit);
        $this->assertSame('km', $doubleInverted->finalUnit);
        $this->assertEqualsWithDelta(0.001, $doubleInverted->multiplier->value, 1e-15);
        $this->assertEqualsWithDelta(0.0, $doubleInverted->offset->value, 1e-15);
    }

    /**
     * Test invert round-trip with offset.
     */
    public function testInvertRoundTripWithOffset(): void
    {
        $conv = new Conversion('C', 'F', 1.8, 32.0);
        $inverted = $conv->invert();

        // Apply conversion and inverse
        $celsius = 100.0;
        $fahrenheit = $conv->apply($celsius);
        $backToCelsius = $inverted->apply($fahrenheit);

        $this->assertInstanceOf(FloatWithError::class, $backToCelsius);
        $this->assertEqualsWithDelta($celsius, $backToCelsius->value, 1e-10);
    }

    // endregion

    // region combineSequential() tests (initial->common, common->final)

    /**
     * Test combineSequential chains units correctly.
     */
    public function testCombineSequentialChainsUnits(): void
    {
        $conv1 = new Conversion('m', 'km', 0.001);      // m -> km
        $conv2 = new Conversion('km', 'mi', 0.621371);  // km -> mi

        $combined = $conv1->combineSequential($conv2);

        $this->assertSame('m', $combined->initialUnit);
        $this->assertSame('mi', $combined->finalUnit);
    }

    /**
     * Test combineSequential with simple multipliers.
     */
    public function testCombineSequentialSimpleMultipliers(): void
    {
        $conv1 = new Conversion('mm', 'cm', 0.1);
        $conv2 = new Conversion('cm', 'm', 0.01);

        $combined = $conv1->combineSequential($conv2);

        // m = mm * 0.1 * 0.01 = mm * 0.001
        $this->assertSame(0.001, $combined->multiplier->value);
        $this->assertSame(0.0, $combined->offset->value);
    }

    /**
     * Test combineSequential with offsets.
     */
    public function testCombineSequentialWithOffsets(): void
    {
        // a -> b: b = a * 2 + 3
        $conv1 = new Conversion('a', 'b', 2.0, 3.0);
        // b -> c: c = b * 4 + 5
        $conv2 = new Conversion('b', 'c', 4.0, 5.0);

        $combined = $conv1->combineSequential($conv2);

        // c = (a * 2 + 3) * 4 + 5 = a * 8 + 12 + 5 = a * 8 + 17
        $this->assertSame(8.0, $combined->multiplier->value);
        $this->assertSame(17.0, $combined->offset->value);
    }

    // endregion

    // region combineConvergent() tests (initial->common, final->common)

    /**
     * Test combineConvergent chains units correctly.
     */
    public function testCombineConvergentChainsUnits(): void
    {
        $conv1 = new Conversion('m', 'cm', 100.0);   // m -> cm
        $conv2 = new Conversion('in', 'cm', 2.54);   // in -> cm

        $combined = $conv1->combineConvergent($conv2);

        $this->assertSame('m', $combined->initialUnit);
        $this->assertSame('in', $combined->finalUnit);
    }

    /**
     * Test combineConvergent with simple multipliers.
     */
    public function testCombineConvergentSimpleMultipliers(): void
    {
        $conv1 = new Conversion('m', 'cm', 100.0);
        $conv2 = new Conversion('in', 'cm', 2.54);

        $combined = $conv1->combineConvergent($conv2);

        // in = m * (100 / 2.54) = m * 39.370...
        $this->assertEqualsWithDelta(100.0 / 2.54, $combined->multiplier->value, 1e-10);
        $this->assertSame(0.0, $combined->offset->value);
    }

    /**
     * Test combineConvergent with offsets.
     */
    public function testCombineConvergentWithOffsets(): void
    {
        // a -> c: c = a * 2 + 3
        $conv1 = new Conversion('a', 'c', 2.0, 3.0);
        // b -> c: c = b * 4 + 5
        $conv2 = new Conversion('b', 'c', 4.0, 5.0);

        $combined = $conv1->combineConvergent($conv2);

        // c = a * 2 + 3 and c = b * 4 + 5
        // So: a * 2 + 3 = b * 4 + 5
        // b = (a * 2 + 3 - 5) / 4 = a * (2/4) + (-2/4) = a * 0.5 - 0.5
        $this->assertSame(0.5, $combined->multiplier->value);
        $this->assertSame(-0.5, $combined->offset->value);
    }

    // endregion

    // region combineDivergent() tests (common->initial, common->final)

    /**
     * Test combineDivergent chains units correctly.
     */
    public function testCombineDivergentChainsUnits(): void
    {
        $conv1 = new Conversion('cm', 'm', 0.01);    // cm -> m
        $conv2 = new Conversion('cm', 'in', 0.3937); // cm -> in

        $combined = $conv1->combineDivergent($conv2);

        $this->assertSame('m', $combined->initialUnit);
        $this->assertSame('in', $combined->finalUnit);
    }

    /**
     * Test combineDivergent with simple multipliers.
     */
    public function testCombineDivergentSimpleMultipliers(): void
    {
        $conv1 = new Conversion('cm', 'm', 0.01);
        $conv2 = new Conversion('cm', 'in', 0.3937);

        $combined = $conv1->combineDivergent($conv2);

        // in = m * (0.3937 / 0.01) = m * 39.37
        $this->assertEqualsWithDelta(39.37, $combined->multiplier->value, 1e-10);
        $this->assertSame(0.0, $combined->offset->value);
    }

    /**
     * Test combineDivergent with offsets.
     */
    public function testCombineDivergentWithOffsets(): void
    {
        // c -> a: a = c * 2 + 3
        $conv1 = new Conversion('c', 'a', 2.0, 3.0);
        // c -> b: b = c * 4 + 5
        $conv2 = new Conversion('c', 'b', 4.0, 5.0);

        $combined = $conv1->combineDivergent($conv2);

        // a = c * 2 + 3 and b = c * 4 + 5
        // From a = c * 2 + 3, we get c = (a - 3) / 2
        // So b = ((a - 3) / 2) * 4 + 5 = a * 2 - 6 + 5 = a * 2 - 1
        $this->assertSame(2.0, $combined->multiplier->value);
        $this->assertSame(-1.0, $combined->offset->value);
    }

    // endregion

    // region combineOpposite() tests (common->initial, final->common)

    /**
     * Test combineOpposite chains units correctly.
     */
    public function testCombineOppositeChainsUnits(): void
    {
        $conv1 = new Conversion('cm', 'm', 0.01);   // cm -> m
        $conv2 = new Conversion('in', 'cm', 2.54);  // in -> cm

        $combined = $conv1->combineOpposite($conv2);

        $this->assertSame('m', $combined->initialUnit);
        $this->assertSame('in', $combined->finalUnit);
    }

    /**
     * Test combineOpposite with simple multipliers.
     */
    public function testCombineOppositeSimpleMultipliers(): void
    {
        $conv1 = new Conversion('cm', 'm', 0.01);
        $conv2 = new Conversion('in', 'cm', 2.54);

        $combined = $conv1->combineOpposite($conv2);

        // From cm -> m: m = cm * 0.01, so cm = m / 0.01 = m * 100
        // From in -> cm: cm = in * 2.54
        // So: m * 100 = in * 2.54
        // in = m * (100 / 2.54) = m * 39.370...
        $this->assertEqualsWithDelta(100.0 / 2.54, $combined->multiplier->value, 1e-10);
        $this->assertEqualsWithDelta(0.0, $combined->offset->value, 1e-10);
    }

    /**
     * Test combineOpposite with offsets.
     */
    public function testCombineOppositeWithOffsets(): void
    {
        // c -> a: a = c * 2 + 3
        $conv1 = new Conversion('c', 'a', 2.0, 3.0);
        // b -> c: c = b * 4 + 5
        $conv2 = new Conversion('b', 'c', 4.0, 5.0);

        $combined = $conv1->combineOpposite($conv2);

        // From a = c * 2 + 3, we get c = (a - 3) / 2
        // From c = b * 4 + 5, we get (a - 3) / 2 = b * 4 + 5
        // So: a - 3 = (b * 4 + 5) * 2 = b * 8 + 10
        // a = b * 8 + 13
        // Therefore: b = (a - 13) / 8 = a / 8 - 13/8
        $this->assertSame(0.125, $combined->multiplier->value);
        $this->assertSame(-1.625, $combined->offset->value);
    }

    // endregion

    // region __toString() tests

    /**
     * Test toString with simple conversion.
     */
    public function testToStringSimple(): void
    {
        $conv = new Conversion('m', 'km', 0.001);

        $str = (string)$conv;

        $this->assertStringContainsString('km = m', $str);
        $this->assertStringContainsString('0.001', $str);
        $this->assertStringContainsString('error score', $str);
    }

    /**
     * Test toString omits multiplier of 1.
     */
    public function testToStringOmitsMultiplierOfOne(): void
    {
        $conv = new Conversion('a', 'b', 1.0, 5.0);

        $str = (string)$conv;

        $this->assertStringContainsString('b = a', $str);
        $this->assertStringNotContainsString('* 1', $str);
        $this->assertStringContainsString('+ 5', $str);
    }

    /**
     * Test toString omits offset of 0.
     */
    public function testToStringOmitsOffsetOfZero(): void
    {
        $conv = new Conversion('m', 'cm', 100.0);

        $str = (string)$conv;

        $this->assertStringContainsString('cm = m * 100', $str);
        $this->assertStringNotContainsString('+ 0', $str);
        $this->assertStringNotContainsString('- 0', $str);
    }

    /**
     * Test toString with negative offset.
     */
    public function testToStringWithNegativeOffset(): void
    {
        $conv = new Conversion('a', 'b', 2.0, -5.0);

        $str = (string)$conv;

        $this->assertStringContainsString('b = a * 2', $str);
        $this->assertStringContainsString('- 5', $str);
    }

    /**
     * Test toString with positive offset.
     */
    public function testToStringWithPositiveOffset(): void
    {
        $conv = new Conversion('C', 'F', 1.8, 32.0);

        $str = (string)$conv;

        $this->assertStringContainsString('F = C * 1.8', $str);
        $this->assertStringContainsString('+ 32', $str);
    }

    /**
     * Test toString shows error score.
     */
    public function testToStringShowsErrorScore(): void
    {
        $multiplier = new FloatWithError(2.0, 0.01);
        $offset = new FloatWithError(10.0, 0.1);
        $conv = new Conversion('a', 'b', $multiplier, $offset);

        $str = (string)$conv;

        $this->assertStringContainsString('error score: 0.11', $str);
    }

    // endregion
}
