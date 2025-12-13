<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests;

use Galaxon\Units\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Tests for Unit class.
 */
#[CoversClass(Unit::class)]
final class UnitTest extends TestCase
{
    // region Constructor tests

    /**
     * Test constructor with base unit (no prefix, no exponent).
     */
    public function testConstructorWithBaseUnit(): void
    {
        $unit = new Unit('m');

        $this->assertSame('m', $unit->base);
        $this->assertSame('', $unit->prefix);
        $this->assertSame(1.0, $unit->prefixMultiplier);
        $this->assertSame(1, $unit->exponent);
    }

    /**
     * Test constructor with prefix.
     */
    public function testConstructorWithPrefix(): void
    {
        $unit = new Unit('m', 'k', 1000);

        $this->assertSame('m', $unit->base);
        $this->assertSame('k', $unit->prefix);
        $this->assertSame(1000.0, $unit->prefixMultiplier);
        $this->assertSame(1, $unit->exponent);
    }

    /**
     * Test constructor with positive exponent.
     */
    public function testConstructorWithPositiveExponent(): void
    {
        $unit = new Unit('m2');

        $this->assertSame('m', $unit->base);
        $this->assertSame(2, $unit->exponent);
    }

    /**
     * Test constructor with negative exponent.
     */
    public function testConstructorWithNegativeExponent(): void
    {
        $unit = new Unit('s-2');

        $this->assertSame('s', $unit->base);
        $this->assertSame(-2, $unit->exponent);
    }

    /**
     * Test constructor with prefix and exponent.
     */
    public function testConstructorWithPrefixAndExponent(): void
    {
        $unit = new Unit('m2', 'k', 1000);

        $this->assertSame('m', $unit->base);
        $this->assertSame('k', $unit->prefix);
        $this->assertSame(1000.0, $unit->prefixMultiplier);
        $this->assertSame(2, $unit->exponent);
    }

    /**
     * Test constructor with float prefix multiplier.
     */
    public function testConstructorWithFloatPrefixMultiplier(): void
    {
        $unit = new Unit('m', 'm', 0.001);

        $this->assertSame('m', $unit->prefix);
        $this->assertSame(0.001, $unit->prefixMultiplier);
    }

    /**
     * Test constructor throws for invalid unit format.
     */
    public function testConstructorThrowsForInvalidFormat(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid unit');

        new Unit('123abc');
    }

    /**
     * Test constructor throws for exponent of zero.
     */
    public function testConstructorThrowsForExponentZero(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid exponent 0');

        new Unit('m0');
    }

    /**
     * Test constructor throws for exponent of one (should be omitted).
     */
    public function testConstructorThrowsForExponentOne(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid exponent 1');

        new Unit('m1');
    }

    /**
     * Test constructor throws for exponent out of range.
     */
    public function testConstructorThrowsForExponentOutOfRange(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid exponent 10');

        new Unit('m10');
    }

    // endregion

    // region Computed property tests

    /**
     * Test derived property for base unit.
     */
    public function testDerivedPropertyForBaseUnit(): void
    {
        $unit = new Unit('m');

        $this->assertSame('m', $unit->derived);
    }

    /**
     * Test derived property with exponent.
     */
    public function testDerivedPropertyWithExponent(): void
    {
        $unit = new Unit('m2', 'k', 1000);

        $this->assertSame('m2', $unit->derived);
    }

    /**
     * Test prefixed property for base unit (no prefix).
     */
    public function testPrefixedPropertyForBaseUnit(): void
    {
        $unit = new Unit('m');

        $this->assertSame('m', $unit->prefixed);
    }

    /**
     * Test prefixed property with prefix.
     */
    public function testPrefixedPropertyWithPrefix(): void
    {
        $unit = new Unit('m', 'k', 1000);

        $this->assertSame('km', $unit->prefixed);
    }

    /**
     * Test prefixed property with prefix and exponent.
     */
    public function testPrefixedPropertyWithPrefixAndExponent(): void
    {
        $unit = new Unit('m2', 'k', 1000);

        $this->assertSame('km2', $unit->prefixed);
    }

    /**
     * Test multiplier property for base unit.
     */
    public function testMultiplierPropertyForBaseUnit(): void
    {
        $unit = new Unit('m');

        $this->assertSame(1.0, $unit->multiplier);
    }

    /**
     * Test multiplier property with prefix (exponent 1).
     */
    public function testMultiplierPropertyWithPrefix(): void
    {
        $unit = new Unit('m', 'k', 1000);

        // 1000^1 = 1000
        $this->assertSame(1000.0, $unit->multiplier);
    }

    /**
     * Test multiplier property with prefix and exponent (prefix squared).
     */
    public function testMultiplierPropertyWithPrefixAndExponent(): void
    {
        $unit = new Unit('m2', 'k', 1000);

        // 1000^2 = 1,000,000
        $this->assertSame(1e6, $unit->multiplier);
    }

    /**
     * Test multiplier property with small prefix and exponent.
     */
    public function testMultiplierPropertyWithSmallPrefixAndExponent(): void
    {
        $unit = new Unit('m2', 'c', 0.01);

        // 0.01^2 = 0.0001
        $this->assertSame(1e-4, $unit->multiplier);
    }

    /**
     * Test multiplier property with negative exponent.
     */
    public function testMultiplierPropertyWithNegativeExponent(): void
    {
        $unit = new Unit('s-2', 'm', 0.001);

        // 0.001^-2 = 1,000,000
        $this->assertSame(1e6, $unit->multiplier);
    }

    // endregion

    // region __toString tests

    /**
     * Test __toString for base unit.
     */
    public function testToStringForBaseUnit(): void
    {
        $unit = new Unit('m');

        $this->assertSame('m', (string)$unit);
    }

    /**
     * Test __toString with prefix.
     */
    public function testToStringWithPrefix(): void
    {
        $unit = new Unit('m', 'k', 1000);

        $this->assertSame('km', (string)$unit);
    }

    /**
     * Test __toString with exponent (superscript).
     */
    public function testToStringWithExponent(): void
    {
        $unit = new Unit('m2');

        $this->assertSame('m²', (string)$unit);
    }

    /**
     * Test __toString with prefix and exponent.
     */
    public function testToStringWithPrefixAndExponent(): void
    {
        $unit = new Unit('m2', 'k', 1000);

        $this->assertSame('km²', (string)$unit);
    }

    /**
     * Test __toString with negative exponent.
     */
    public function testToStringWithNegativeExponent(): void
    {
        $unit = new Unit('s-2');

        $this->assertSame('s⁻²', (string)$unit);
    }

    /**
     * Test __toString converts 'u' prefix to 'μ'.
     */
    public function testToStringConvertsMicroPrefix(): void
    {
        $unit = new Unit('m', 'u', 1e-6);

        $this->assertSame('μm', (string)$unit);
    }

    /**
     * Test __toString with micro prefix and exponent.
     */
    public function testToStringWithMicroPrefixAndExponent(): void
    {
        $unit = new Unit('m2', 'u', 1e-6);

        $this->assertSame('μm²', (string)$unit);
    }

    // endregion
}
