<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests\MeasurementTypes;

use Galaxon\Units\MeasurementTypes\Mass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Mass measurement class.
 */
#[CoversClass(Mass::class)]
final class MassTest extends TestCase
{
    // region Constructor and basic unit tests

    /**
     * Test constructor with base unit.
     */
    public function testConstructorWithBaseUnit(): void
    {
        $mass = new Mass(100, 'g');

        $this->assertSame(100.0, $mass->value);
        $this->assertSame('g', $mass->unit);
    }

    /**
     * Test constructor with prefixed unit.
     */
    public function testConstructorWithPrefixedUnit(): void
    {
        $mass = new Mass(5, 'kg');

        $this->assertSame(5.0, $mass->value);
        $this->assertSame('kg', $mass->unit);
    }

    // endregion

    // region Metric prefix conversion tests

    /**
     * Test kg to g conversion.
     */
    public function testKgToGConversion(): void
    {
        $mass = new Mass(1, 'kg');

        $result = $mass->to('g');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    /**
     * Test g to kg conversion.
     */
    public function testGToKgConversion(): void
    {
        $mass = new Mass(1000, 'g');

        $result = $mass->to('kg');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    /**
     * Test mg to g conversion.
     */
    public function testMgToGConversion(): void
    {
        $mass = new Mass(1000, 'mg');

        $result = $mass->to('g');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    /**
     * Test tonne to kg conversion.
     */
    public function testTonneToKgConversion(): void
    {
        $mass = new Mass(1, 't');

        $result = $mass->to('kg');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    /**
     * Test kilotonne to tonne conversion (large metric prefix on tonne).
     */
    public function testKilotonneToTonneConversion(): void
    {
        $mass = new Mass(1, 'kt');

        $result = $mass->to('t');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    // endregion

    // region Metric-imperial conversion tests

    /**
     * Test pound to gram conversion.
     */
    public function testPoundToGramConversion(): void
    {
        $mass = new Mass(1, 'lb');

        $result = $mass->to('g');

        $this->assertEqualsWithDelta(453.59237, $result->value, 1e-5);
    }

    /**
     * Test kg to pound conversion.
     */
    public function testKgToPoundConversion(): void
    {
        $mass = new Mass(1, 'kg');

        $result = $mass->to('lb');

        // 1 kg = 1000 g / 453.59237 g/lb ≈ 2.20462 lb
        $this->assertEqualsWithDelta(2.20462262, $result->value, 1e-5);
    }

    /**
     * Test pound to ounce conversion.
     */
    public function testPoundToOunceConversion(): void
    {
        $mass = new Mass(1, 'lb');

        $result = $mass->to('oz');

        $this->assertEqualsWithDelta(16.0, $result->value, 1e-10);
    }

    /**
     * Test stone to pound conversion.
     */
    public function testStoneToPoundConversion(): void
    {
        $mass = new Mass(1, 'st');

        $result = $mass->to('lb');

        $this->assertEqualsWithDelta(14.0, $result->value, 1e-10);
    }

    /**
     * Test US ton to pound conversion.
     */
    public function testUsTonToPoundConversion(): void
    {
        $mass = new Mass(1, 'ton');

        $result = $mass->to('lb');

        // US short ton = 2000 lb
        $this->assertEqualsWithDelta(2000.0, $result->value, 1e-10);
    }

    // endregion

    // region Physical constants tests

    /**
     * Test electron mass constant.
     */
    public function testElectronMass(): void
    {
        $electron = Mass::electronMass();

        $this->assertEqualsWithDelta(9.1093837015e-31, $electron->value, 1e-40);
        $this->assertSame('kg', $electron->unit);
    }

    /**
     * Test proton mass constant.
     */
    public function testProtonMass(): void
    {
        $proton = Mass::protonMass();

        $this->assertEqualsWithDelta(1.67262192369e-27, $proton->value, 1e-36);
        $this->assertSame('kg', $proton->unit);
    }

    /**
     * Test neutron mass constant.
     */
    public function testNeutronMass(): void
    {
        $neutron = Mass::neutronMass();

        $this->assertEqualsWithDelta(1.67492749804e-27, $neutron->value, 1e-36);
        $this->assertSame('kg', $neutron->unit);
    }

    // endregion

    // region Round-trip conversion tests

    /**
     * Test round-trip conversion preserves value.
     */
    public function testRoundTripConversion(): void
    {
        $original = new Mass(123.456, 'kg');

        $result = $original->to('lb')->to('oz')->to('g')->to('mg')->to('kg');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-10);
    }

    // endregion

    // region useBritishUnits tests

    /**
     * Test useBritishUnits changes ton to long ton (2240 lb).
     */
    public function testUseBritishUnitsChangesTonConversion(): void
    {
        // Call useBritishUnits to switch from US short ton to British long ton.
        Mass::useBritishUnits();

        $mass = new Mass(1, 'ton');
        $result = $mass->to('lb');

        // British long ton = 2240 lb (not 2000 lb).
        $this->assertEqualsWithDelta(2240.0, $result->value, 1e-10);

        // Reset back to US ton for other tests.
        Mass::getUnitConverter()->addConversion('ton', 'lb', 2000);
    }

    // endregion
}
