<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests\MeasurementTypes;

use Galaxon\Units\MeasurementTypes\Memory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Memory measurement class.
 *
 * Memory uses PREFIX_CODE_LARGE which includes both metric (KB, MB, GB)
 * and binary (KiB, MiB, GiB) prefixes.
 */
#[CoversClass(Memory::class)]
final class MemoryTest extends TestCase
{
    // region Constructor and basic unit tests

    /**
     * Test constructor with base unit (byte).
     */
    public function testConstructorWithByte(): void
    {
        $memory = new Memory(1024, 'B');

        $this->assertSame(1024.0, $memory->value);
        $this->assertSame('B', $memory->unit);
    }

    /**
     * Test constructor with bit.
     */
    public function testConstructorWithBit(): void
    {
        $memory = new Memory(8, 'b');

        $this->assertSame(8.0, $memory->value);
        $this->assertSame('b', $memory->unit);
    }

    // endregion

    // region Byte to bit conversion tests

    /**
     * Test byte to bit conversion.
     */
    public function testByteToBitConversion(): void
    {
        $memory = new Memory(1, 'B');

        $result = $memory->to('b');

        $this->assertEqualsWithDelta(8.0, $result->value, 1e-10);
    }

    /**
     * Test bit to byte conversion.
     */
    public function testBitToByteConversion(): void
    {
        $memory = new Memory(8, 'b');

        $result = $memory->to('B');

        $this->assertEqualsWithDelta(1.0, $result->value, 1e-10);
    }

    // endregion

    // region Metric prefix conversion tests (decimal: 1000-based)

    /**
     * Test KB to B conversion (metric: 1 KB = 1000 B).
     */
    public function testKbToBConversion(): void
    {
        $memory = new Memory(1, 'kB');

        $result = $memory->to('B');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    /**
     * Test MB to B conversion (metric: 1 MB = 1,000,000 B).
     */
    public function testMbToBConversion(): void
    {
        $memory = new Memory(1, 'MB');

        $result = $memory->to('B');

        $this->assertEqualsWithDelta(1e6, $result->value, 1e-4);
    }

    /**
     * Test GB to B conversion (metric: 1 GB = 1,000,000,000 B).
     */
    public function testGbToBConversion(): void
    {
        $memory = new Memory(1, 'GB');

        $result = $memory->to('B');

        $this->assertEqualsWithDelta(1e9, $result->value, 1);
    }

    /**
     * Test TB to GB conversion.
     */
    public function testTbToGbConversion(): void
    {
        $memory = new Memory(1, 'TB');

        $result = $memory->to('GB');

        $this->assertEqualsWithDelta(1000.0, $result->value, 1e-10);
    }

    // endregion

    // region Binary prefix conversion tests (binary: 1024-based)

    /**
     * Test KiB to B conversion (binary: 1 KiB = 1024 B).
     */
    public function testKibToBConversion(): void
    {
        $memory = new Memory(1, 'KiB');

        $result = $memory->to('B');

        $this->assertEqualsWithDelta(1024.0, $result->value, 1e-10);
    }

    /**
     * Test MiB to B conversion (binary: 1 MiB = 1,048,576 B).
     */
    public function testMibToBConversion(): void
    {
        $memory = new Memory(1, 'MiB');

        $result = $memory->to('B');

        $this->assertEqualsWithDelta(1048576.0, $result->value, 1e-4);
    }

    /**
     * Test GiB to B conversion (binary: 1 GiB = 1,073,741,824 B).
     */
    public function testGibToBConversion(): void
    {
        $memory = new Memory(1, 'GiB');

        $result = $memory->to('B');

        $this->assertEqualsWithDelta(1073741824.0, $result->value, 1);
    }

    /**
     * Test TiB to GiB conversion.
     */
    public function testTibToGibConversion(): void
    {
        $memory = new Memory(1, 'TiB');

        $result = $memory->to('GiB');

        $this->assertEqualsWithDelta(1024.0, $result->value, 1e-10);
    }

    // endregion

    // region Metric to binary conversion tests

    /**
     * Test GB to GiB conversion.
     *
     * 1 GB = 1,000,000,000 B
     * 1 GiB = 1,073,741,824 B
     * 1 GB = 1,000,000,000 / 1,073,741,824 GiB ≈ 0.931 GiB
     */
    public function testGbToGibConversion(): void
    {
        $memory = new Memory(1, 'GB');

        $result = $memory->to('GiB');

        $this->assertEqualsWithDelta(1e9 / 1073741824.0, $result->value, 1e-6);
    }

    /**
     * Test GiB to GB conversion.
     *
     * 1 GiB ≈ 1.074 GB
     */
    public function testGibToGbConversion(): void
    {
        $memory = new Memory(1, 'GiB');

        $result = $memory->to('GB');

        $this->assertEqualsWithDelta(1073741824.0 / 1e9, $result->value, 1e-6);
    }

    // endregion

    // region Bit prefix conversion tests

    /**
     * Test Mb to b conversion (metric megabit).
     */
    public function testMbToBitConversion(): void
    {
        $memory = new Memory(1, 'Mb');

        $result = $memory->to('b');

        $this->assertEqualsWithDelta(1e6, $result->value, 1e-4);
    }

    /**
     * Test Gb to MB conversion (gigabit to megabyte).
     *
     * 1 Gb = 1,000,000,000 bits = 125,000,000 bytes = 125 MB
     */
    public function testGbToMBConversion(): void
    {
        $memory = new Memory(1, 'Gb');

        $result = $memory->to('MB');

        $this->assertEqualsWithDelta(125.0, $result->value, 1e-6);
    }

    // endregion

    // region Round-trip conversion tests

    /**
     * Test round-trip conversion preserves value.
     */
    public function testRoundTripConversion(): void
    {
        $original = new Memory(123.456, 'GB');

        $result = $original->to('GiB')->to('MiB')->to('KiB')->to('B')->to('b')->to('MB')->to('GB');

        $this->assertEqualsWithDelta($original->value, $result->value, 1e-6);
    }

    // endregion
}
