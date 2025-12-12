<?php

declare(strict_types=1);

namespace Galaxon\Units\Tests\MeasurementTypes;

use DivisionByZeroError;
use Galaxon\Core\Floats;
use Galaxon\Units\MeasurementTypes\Angle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;
use ValueError;

#[CoversClass(Angle::class)]
final class AngleTest extends TestCase
{
    /**
     * Assert that two float values are equal within a delta tolerance.
     *
     * @param float $expected The expected value.
     * @param float $actual The actual value.
     * @param float $delta The maximum difference allowed (default: RAD_EPSILON).
     */
    private function assertFloatEquals(float $expected, float $actual, float $delta = Angle::RAD_EPSILON): void
    {
        $this->assertEqualsWithDelta($expected, $actual, $delta);
    }

    /**
     * Assert that two Angle instances are equal.
     *
     * @param Angle $a The first angle.
     * @param Angle $b The second angle.
     */
    private function assertAngleEquals(Angle $a, Angle $b): void
    {
        $this->assertTrue($a->approxEqual($b), "Angles differ: {$a} vs {$b}");
    }

    /**
     * Test that creating an angle with infinity throws ValueError.
     */
    public function testConstructorWithInfinity(): void
    {
        $this->expectException(ValueError::class);
        new Angle(INF, 'rad');
    }

    /**
     * Test that creating an angle with NAN throws ValueError.
     */
    public function testConstructorWithNan(): void
    {
        $this->expectException(ValueError::class);
        new Angle(NAN, 'deg');
    }

    /**
     * Test that creating an angle with an invalid unit throws ValueError.
     */
    public function testConstructorWithInvalidUnit(): void
    {
        $this->expectException(ValueError::class);
        new Angle(45, 'invalid');
    }

    /**
     * Test that the constructor and getters work correctly together.
     *
     * Creates an angle using the constructor and verifies it can be converted
     * to all other units with correct values.
     */
    public function testConstructorAndGettersRoundtrip(): void
    {
        $a = new Angle(180.0, 'deg');
        $this->assertFloatEquals(M_PI, $a->to('rad')->value);
        $this->assertFloatEquals(180.0, $a->to('deg')->value);
        $this->assertFloatEquals(200.0, $a->to('grad')->value);
        $this->assertFloatEquals(0.5, $a->to('turn')->value);
    }

    /**
     * Test conversion to and from degrees, arcminutes, and arcseconds (DMS).
     *
     * Verifies that DMS values round-trip correctly and that floating-point
     * precision near boundaries produces expected results.
     */
    public function testPartsRoundtripAndCarry(): void
    {
        $a = Angle::fromParts(12, 34, 56);
        $parts = $a->toParts('arcsec');
        $this->assertEquals(1, $parts['sign']);
        $this->assertEquals(12, $parts['deg']);
        $this->assertEquals(34, $parts['arcmin']);
        $this->assertEquals(56, $parts['arcsec']);

        // Verify floating-point precision at seconds and minutes boundaries.
        $b = new Angle(29.999999999, 'deg');
        $parts2 = $b->toParts('arcsec');
        $this->assertEquals(1, $parts2['sign']);
        $this->assertEquals(29, $parts2['deg']);
        $this->assertEquals(59, $parts2['arcmin']);
        $this->assertFloatEquals(59.9999964, $parts2['arcsec']);

        // Verify floating-point precision at minutes boundary.
        $b = new Angle(29.999999999, 'deg');
        $parts3 = $b->toParts('arcmin');
        $this->assertEquals(1, $parts3['sign']);
        $this->assertEquals(29, $parts3['deg']);
        $this->assertFloatEquals(59.99999994, $parts3['arcmin']);

        // Test that invalid smallest unit throws ValueError.
        $this->expectException(ValueError::class);
        $x = $b->toParts('invalid');
    }

    /**
     * Test toDMS() with degrees only (UNIT_DEGREE).
     *
     * Verifies that requesting only degrees returns a single-element array
     * with the correct decimal degree value.s
     */
    public function testToPartsWithDegreesOnly(): void
    {
        $a = new Angle(45.5, 'deg');
        $parts = $a->toParts('deg');
        $this->assertEquals(1, $parts['sign']);
        $this->assertFloatEquals(45.5, $parts['deg']);
    }

    /**
     * Test toDMS() with negative angles.
     *
     * Verifies that negative angles correctly apply the sign to all components
     * when converted to DMS format.
     */
    public function testToPartsWithNegativeAngles(): void
    {
        $a = Angle::fromParts(12, 34, 56, -1);

        // Test arcseconds
        $parts = $a->toParts('arcsec');
        $this->assertEquals(-1, $parts['sign']);
        $this->assertEquals(12, $parts['deg']);
        $this->assertEquals(34, $parts['arcmin']);
        $this->assertEquals(56, $parts['arcsec']);

        // Test arcminutes
        $parts2 = $a->toParts('arcmin');
        $this->assertEquals(-1, $parts2['sign']);
        $this->assertEquals(12, $parts2['deg']);
        $this->assertFloatEquals(34.933333, $parts2['arcmin'], 1e-6);
    }

    /**
     * Test toDMS() with zero angle.
     *
     * Verifies that a zero angle converts correctly to DMS format with
     * all zero components.
     */
    public function testToPartsWithZeroAngle(): void
    {
        $a = new Angle(0, 'deg');
        $parts = $a->toParts('arcsec');
        $this->assertEquals(1, $parts['sign']);
        $this->assertEquals(0, $parts['deg']);
        $this->assertEquals(0, $parts['arcmin']);
        $this->assertEquals(0, $parts['arcsec']);
    }

    /**
     * Test parsing of angle strings in CSS units and DMS format.
     *
     * Verifies that the parse() method correctly handles rad, deg, grad, turn units
     * and DMS notation with both Unicode and ASCII symbols.
     */
    public function testParsingCssUnitsAndDms(): void
    {
        $this->assertAngleEquals(new Angle(12, 'deg'), Angle::parse('12deg'));
        $this->assertAngleEquals(new Angle(12, 'deg'), Angle::parse('12 deg'));
        $this->assertAngleEquals(new Angle(0.5, 'turn'), Angle::parse('0.5 turn'));
        $this->assertAngleEquals(new Angle(M_PI, 'rad'), Angle::parse(M_PI . 'rad'));

        // Unicode symbols (°, ′, ″).
        $this->assertAngleEquals(Angle::fromParts(12, 34, 56), Angle::parse('12° 34′ 56″'));
        // ASCII fallback (°, ', ").
        $this->assertAngleEquals(Angle::fromParts(12, 34, 56, -1), Angle::parse("-12°34'56\""));
    }

    /**
     * Test that parsing empty input throws ValueError.
     */
    public function testParseRejectsBadInputs(): void
    {
        $this->expectException(ValueError::class);
        Angle::parse('');
    }

    /**
     * Test that parsing invalid input throws ValueError.
     */
    public function testParseRejectsInvalidString(): void
    {
        $this->expectException(ValueError::class);
        Angle::parse('456 bananas');
    }

    /**
     * Test wrapping angles into unsigned and signed ranges with non-boundary values.
     *
     * Verifies that the wrap() method correctly normalizes angles to the
     * appropriate range. This tests general behavior, not boundary conditions.
     */
    public function testWrapUnsignedAndSigned(): void
    {
        // Unsigned range [0, τ) - test values in the middle of ranges
        $a = new Angle(3 * M_PI, 'rad');  // 1.5 turns
        $this->assertFloatEquals(M_PI, $a->wrap(false)->to('rad')->value);

        $b = new Angle(-3 * M_PI / 2, 'rad');  // -0.75 turns
        $this->assertFloatEquals(M_PI / 2, $b->wrap(false)->to('rad')->value);

        // Signed range (-π, π] - test values in quadrants
        $c = new Angle(5 * M_PI / 4, 'rad');  // 225 degrees, should wrap to -135 degrees
        $this->assertFloatEquals(-3 * M_PI / 4, $c->wrap(true)->to('rad')->value);

        $d = new Angle(-5 * M_PI / 4, 'rad');  // -225 degrees, should wrap to 135 degrees
        $this->assertFloatEquals(3 * M_PI / 4, $d->wrap(true)->to('rad')->value);
    }

    /**
     * Test arithmetic operations (add, sub, mul, div).
     *
     * Verifies that angles can be added, subtracted, and scaled with correct results.
     */
    public function testArithmetic(): void
    {
        $a = new Angle(10, 'deg');

        $sum = $a->add(new Angle(20, 'deg'));
        $this->assertFloatEquals(30.0, $sum->to('deg')->value);

        $diff = $a->sub(new Angle(40, 'deg'));
        $this->assertFloatEquals(-30.0, $diff->to('deg')->value);

        $scaled = $a->mul(3)->div(2);
        $this->assertFloatEquals(15.0, $scaled->to('deg')->value);
    }

    /**
     * Test that multiplying by infinity throws ValueError.
     */
    public function testMulWithNonFiniteParameter(): void
    {
        $a = new Angle(10, 'deg');
        $this->expectException(ValueError::class);
        $a->mul(INF);
    }

    /**
     * Test that dividing by NAN throws ValueError.
     */
    public function testDivWithNonFiniteParameters(): void
    {
        $a = new Angle(10, 'deg');
        $this->expectException(ValueError::class);
        $a->div(NAN);
    }

//    /**
//     * Test that wrapping with infinity throws ValueError.
//     */
//    public function testWrapWithNonFiniteParameters(): void
//    {
//        $this->expectException(ValueError::class);
//        Angle::wrapDegrees(INF);
//    }

    /**
     * Test trigonometric functions and their behavior at singularities.
     *
     * Verifies that sin, cos, tan return correct values and that tan
     * produces infinity at 90° as expected.
     */
    public function testTrigAndReciprocalsBehaviour(): void
    {
        $a = new Angle(60, 'deg');
        $this->assertFloatEquals(sqrt(3) / 2, $a->sin());
        $this->assertFloatEquals(0.5, $a->cos());
        $this->assertFloatEquals(sqrt(3), $a->tan());

        // Verify that tan(90°) = INF.
        $t = new Angle(90, 'deg');
        $this->assertTrue(is_infinite($t->tan()));
    }

    /**
     * Test secant, cosecant, and cotangent functions.
     *
     * Verifies that sec, csc, cot return correct values and produce
     * infinity at appropriate singularities.
     */
    public function testSecCscCot(): void
    {
        $a = new Angle(60, 'deg');
        // sec(60°) = 1/cos(60°) = 1/0.5 = 2
        $this->assertFloatEquals(2.0, $a->sec());
        // csc(60°) = 1/sin(60°) = 1/(√3/2) = 2/√3
        $this->assertFloatEquals(2 / sqrt(3), $a->csc());
        // cot(60°) = cos(60°)/sin(60°) = 0.5/(√3/2) = 1/√3
        $this->assertFloatEquals(1 / sqrt(3), $a->cot());

        // Verify singularities.
        $at90 = new Angle(90, 'deg');
        $this->assertTrue(is_infinite($at90->sec())); // sec(90°) = INF
        $this->assertFloatEquals(1.0, $at90->csc()); // csc(90°) = 1

        $at0 = new Angle(0, 'deg');
        $this->assertFloatEquals(1.0, $at0->sec()); // sec(0°) = 1
        $this->assertTrue(is_infinite($at0->csc())); // csc(0°) = INF
        $this->assertTrue(is_infinite($at0->cot())); // cot(0°) = INF
    }

    /**
     * Test hyperbolic functions sinh, cosh, tanh.
     *
     * Verifies correct values for basic hyperbolic functions.
     */
    public function testHyperbolicFunctions(): void
    {
        $a = new Angle(1.0, 'rad');
        $this->assertFloatEquals(sinh(1.0), $a->sinh());
        $this->assertFloatEquals(cosh(1.0), $a->cosh());
        $this->assertFloatEquals(tanh(1.0), $a->tanh());

        // At zero.
        $zero = new Angle(0, 'rad');
        $this->assertFloatEquals(0.0, $zero->sinh());
        $this->assertFloatEquals(1.0, $zero->cosh());
        $this->assertFloatEquals(0.0, $zero->tanh());
    }

    /**
     * Test hyperbolic secant, cosecant, and cotangent functions.
     *
     * Verifies that sech, csch, coth return correct values and handle
     * singularities appropriately.
     */
    public function testSechCschCoth(): void
    {
        $a = new Angle(1.0, 'rad');
        // sech(1) = 1/cosh(1)
        $this->assertFloatEquals(1 / cosh(1.0), $a->sech());
        // csch(1) = 1/sinh(1)
        $this->assertFloatEquals(1 / sinh(1.0), $a->csch());
        // coth(1) = cosh(1)/sinh(1)
        $this->assertFloatEquals(cosh(1.0) / sinh(1.0), $a->coth());

        // At zero: csch and coth have singularities.
        $zero = new Angle(0, 'rad');
        $this->assertFloatEquals(1.0, $zero->sech()); // sech(0) = 1/cosh(0) = 1
        $this->assertFloatEquals(INF, $zero->csch()); // csch(0) = INF
        $this->assertFloatEquals(INF, $zero->coth()); // coth(0) = INF
    }

    /**
     * Test formatting angles in various output formats.
     *
     * Verifies that the format() method correctly produces strings in rad, deg,
     * grad, turn, and DMS formats with specified decimal precision.
     */
    public function testFormatVariants(): void
    {
        $a = new Angle(12.5, 'deg');
        $this->assertSame('0.2181661565rad', $a->to('rad')->format('f', 10));
        $this->assertSame('12.50deg', $a->to('deg')->format('f', 2, false));
        $this->assertSame('13.888888889grad', $a->to('grad')->format('f', 9));
        $this->assertSame('0.0347222222 turn', $a->to('turn')->format('f', 10, true, true));

        // DMS via format.
        $this->assertSame('12° 30′ 0″', $a->formatParts('arcsec', 0));

        // Verify that negative decimals value throws ValueError.
        $this->expectException(ValueError::class);
        $a->format('f', -1);
    }

    /**
     * Test DMS formatting when no carry is needed.
     *
     * Verifies that values just below rounding thresholds are formatted
     * correctly without triggering carry to the next unit.
     */
    public function testFormatDmsNoCarryNeeded(): void
    {
        // Values that shouldn't trigger carry
        $a = Angle::fromParts(29, 59, 59.994);
        $this->assertSame('29° 59′ 59.994″', $a->formatParts('arcsec', 3));
    }

    /**
     * Test DMS formatting with carry logic across unit boundaries.
     *
     * Verifies that rounding causes correct carry from seconds to minutes,
     * minutes to degrees, and handles both positive and negative angles.
     */
    public function testFormatDmsWithCarry(): void
    {
        // Test degree rounding (29.9999... → 30°)
        $a = new Angle(29.9999999999, 'deg');
        $this->assertSame('30.000°', $a->formatParts('deg', 3));
        $this->assertSame('30° 0.000′', $a->formatParts('arcmin', 3));
        $this->assertSame('30° 0′ 0.000″', $a->formatParts('arcsec', 3));

        // Test arcminute carry (29° 59.9999′ → 30° 0′)
        $a = Angle::fromParts(29, 59.9999999);
        $this->assertSame('30° 0.000′', $a->formatParts('arcmin', 3));

        // Test arcsecond carry (29° 59′ 59.9999″ → 30° 0′ 0″)
        $a = Angle::fromParts(29, 59, 59.9999999);
        $this->assertSame('30° 0′ 0.000″', $a->formatParts('arcsec', 3));

        // Test double carry (seconds → minutes → degrees)
        $a = Angle::fromParts(29, 59, 59.9999999);
        $this->assertSame('30.000°', $a->formatParts('deg', 3));

        // Test mid-range carry (not at zero boundary)
        $a = Angle::fromParts(45, 59, 59.9995);
        $this->assertSame('46° 0′ 0.000″', $a->formatParts('arcsec', 3));

        // Test negative angle carry
        $a = new Angle(-29.9999999999, 'deg');
        $this->assertSame('-30.000°', $a->formatParts('deg', 3));
        $this->assertSame('-30° 0.000′', $a->formatParts('arcmin', 3));
        $this->assertSame('-30° 0′ 0.000″', $a->formatParts('arcsec', 3));
    }

    /**
     * Set up the test environment with deterministic random seed.
     */
    protected function setUp(): void
    {
        // Deterministic randomness for reproducible tests.
        mt_srand(0xC0FFEE);
    }

    /**
     * Test random round-trip conversions between all angle units.
     *
     * Performs 500 randomized tests converting angles between radians, degrees,
     * gradians, and turns to verify conversion accuracy across a large range.
     */
    public function testRandomRoundtripsRadiansDegreesGradiansTurns(): void
    {
        for ($i = 0; $i < 500; $i++) {
            // Span a large range, including huge magnitudes.
            $rad = Floats::rand(-1e6, 1e6);
            $a = new Angle($rad, 'rad');

            // Verify toX() / fromX() round-trips.
            $this->assertFloatEquals($rad, $a->to('rad')->value);

            $deg = $a->to('deg')->value;
            $this->assertFloatEquals($a->to('rad')->value, new Angle($deg, 'deg')->to('rad')->value);

            $grad = $a->to('grad')->value;
            $this->assertFloatEquals($a->to('rad')->value, new Angle($grad, 'grad')->to('rad')->value);

            $turn = $a->to('turn')->value;
            $this->assertFloatEquals($a->to('rad')->value, new Angle($turn, 'turn')->to('rad')->value);
        }
    }

    /**
     * Test format-then-parse round-trips for all output styles.
     *
     * Performs 200 randomized tests formatting angles in all supported units (rad, deg, grad, turn) and parsing them
     * back to verify that no information is lost in the conversion.
     */
    public function testFormatThenParseRoundtripVariousStyles(): void
    {
        $units = ['rad', 'deg', 'grad', 'turn'];

        for ($i = 0; $i < 200; $i++) {
            $rad = Floats::rand(-1000.0, 1000.0);
            $a = new Angle($rad, 'rad');

            foreach ($units as $unit) {
                // Use max float precision to ensure correct round-trip conversion.
                $s = $a->to($unit)->format('g', 17, false, false);
                $b = Angle::parse($s);

                $this->assertTrue(
                    $a->approxEqual($b),
                    "Format/parse mismatch for unit '{$unit}': {$s} → {$b} vs {$a}"
                );
            }
        }
    }

    /**
     * Test DMS conversion with extreme and out-of-range values.
     *
     * Verifies that fromDegrees() correctly handles arcminutes and arcseconds
     * beyond their normal ranges (0-59) and mixed sign values.
     */
    public function testPartsExtremesAndOutOfRangeParts(): void
    {
        // Minutes/seconds beyond their usual ranges should still compute correctly.
        $a = Angle::fromParts(10, 120, 120); // 10° + 2° + 0.033...° = 12.033...
        $this->assertFloatEquals(12.0333333333, $a->to('deg')->value, 1e-9);

        // Exactly 60 seconds (should carry in formatting).
        $a = Angle::fromParts(29, 59, 60.0);
        $this->assertSame('30° 0′ 0.000″', $a->formatParts('arcsec', 3));
    }

    /**
     * Test parsing with various whitespace, case, and symbol variations.
     *
     * Verifies that the parser handles whitespace tolerance, case insensitivity,
     * both Unicode and ASCII symbols, and rejects invalid input.
     */
    public function testParsingWhitespaceAndAsciiUnicodeSymbols(): void
    {
        $this->assertTrue(new Angle(12, 'deg')->equal(Angle::parse('12 deg')));
        $this->assertTrue(new Angle(0.25, 'turn')->equal(Angle::parse(' 0.25   turn ')));
        $this->assertTrue(new Angle(M_PI, 'rad')->approxEqual(Angle::parse(sprintf('%.12frad', M_PI))));

        // Unicode DMS symbols (°, ′, ″).
        $this->assertTrue(Angle::fromParts(12, 34, 56)->equal(Angle::parse('12° 34′ 56″')));
        // ASCII DMS fallback (°, ', ").
        $this->assertTrue(Angle::fromParts(12, 34, 56, -1)->equal(Angle::parse("-12°34'56\"")));

        // Verify that invalid DMS format throws ValueError.
        $this->expectException(ValueError::class);
        $a = Angle::parse('-');
    }

    /**
     * Test that division by zero throws DivisionByZeroError.
     */
    public function testDivisionByZero(): void
    {
        $a = new Angle(90, 'deg');
        $this->expectException(DivisionByZeroError::class);
        $a->div(0.0);
    }

    /**
     * Test comparison behavior with epsilon tolerance and sign of delta.
     *
     * Verifies that compare() correctly returns -1, 0, or 1 based on the
     * difference between angles.
     */
    public function testCompareWithEpsilonAndDelta(): void
    {
        $a = new Angle(10, 'deg');
        $b = new Angle(20, 'deg');

        // Delta is negative -> a < b.
        $this->assertSame(-1, $a->compare($b));

        // Delta is positive -> b > a.
        $this->assertSame(1, $b->compare($a));
    }
//    /**
//     * Test that wrapDegrees() normalizes values correctly.
//     *
//     * Verifies wrapping behavior for degrees in both unsigned [0, 360) and signed (-180, 180] ranges.
//     */
//    public function testWrapDegreesBehaviour(): void
//    {
//        $this->assertFloatEquals(50.0, Angle::wrapDegrees(410.0, false));
//        $this->assertFloatEquals(150.0, Angle::wrapDegrees(-210.0, true));
//    }
//
//    /**
//     * Test that wrapGradians() normalizes values correctly.
//     *
//     * Verifies wrapping behavior for gradians in both unsigned [0, 400) and signed (-200, 200] ranges.
//     */
//    public function testWrapGradiansBehaviour(): void
//    {
//        $this->assertFloatEquals(50.0, Angle::wrapGradians(450.0, false));
//        $this->assertFloatEquals(190.0, Angle::wrapGradians(-210.0, true));
//    }

//    /**
//     * Test that wrapTurns() normalizes values correctly.
//     *
//     * Verifies wrapping behavior for turns in both unsigned [0, 1) and signed (-0.5, 0.5] ranges.
//     */
//    public function testWrapTurnsBehaviour(): void
//    {
//        $this->assertFloatEquals(0.2, Angle::wrapTurns(1.2, false));
//        $this->assertFloatEquals(0.25, Angle::wrapTurns(-0.75, true));
//    }

    /**
     * Test hyperbolic trigonometric functions.
     *
     * Verifies that sinh, cosh, and tanh methods return values matching
     * PHP's built-in hyperbolic functions.
     */
    public function testHyperbolicTrigFunctions(): void
    {
        $x = 0.5;
        $a = new Angle($x, 'rad');

        $this->assertFloatEquals(sinh($x), $a->sinh());
        $this->assertFloatEquals(cosh($x), $a->cosh());
        $this->assertFloatEquals(tanh($x), $a->tanh());
    }

    /**
     * Test that formatting with an invalid format string throws ValueError.
     */
    public function testFormatInvalidFormatString(): void
    {
        $a = new Angle(45, 'deg');
        $this->expectException(ValueError::class);
        $a->format('invalid');
    }

    /**
     * Test the __toString() magic method.
     *
     * Verifies that casting an angle to string produces the expected
     * format (radians with CSS notation).
     */
    public function testToString(): void
    {
        $a = new Angle(M_PI, 'rad');
        $this->assertMatchesRegularExpression('/^\d+\.\d+\s?rad$/', (string)$a);
    }

    /**
     * Test the equal() equality method.
     *
     * Verifies that equal() correctly identifies equal and unequal angles.
     */
    public function testApproxEqual(): void
    {
        $a = new Angle(10, 'deg');
        $b = new Angle(20, 'deg');
        $c = new Angle(10, 'deg');

        $this->assertTrue($a->equal($c));
        $this->assertFalse($a->equal($b));
    }

    /**
     * Test equal() with epsilon tolerance.
     *
     * Verifies that angles differing by less than RAD_EPSILON are considered equal.
     */
    public function testApproxEqualWithEpsilonTolerance(): void
    {
        $a = new Angle(1.0, 'rad');
        $b = new Angle(1.0 + Angle::RAD_EPSILON / 2, 'rad');

        // Should be equal within epsilon
        $this->assertTrue($a->approxEqual($b));

        // Should not be equal outside epsilon
        $c = new Angle(1.0 + Angle::RAD_EPSILON * 2, 'rad');
        $this->assertFalse($a->approxEqual($c));
    }

    /**
     * Test equal() with non-Angle types returns false.
     *
     * Verifies that equal() gracefully handles invalid types without throwing.
     */
    public function testApproxEqualWithInvalidType(): void
    {
        $a = new Angle(10, 'deg');

        $this->assertFalse($a->equal(10));
        $this->assertFalse($a->equal(10.0));
        $this->assertFalse($a->equal('10deg'));
        $this->assertFalse($a->equal([]));
        $this->assertFalse($a->equal(new stdClass()));
    }

    /**
     * Test compare() with equal angles within epsilon.
     *
     * Verifies that compare() returns 0 for angles within epsilon tolerance.
     */
    public function testApproxCompareEqualWithinEpsilon(): void
    {
        $a = new Angle(1.0, 'rad');
        $b = new Angle(1.0 + Angle::RAD_EPSILON / 2, 'rad');

        $this->assertSame(0, $a->approxCompare($b, 0, Angle::RAD_EPSILON));
    }

    /**
     * Test compare() throws TypeError for non-Angle types.
     *
     * Verifies that compare() throws TypeError when comparing with invalid types.
     */
    public function testCompareWithInvalidTypeThrows(): void
    {
        $a = new Angle(10, 'deg');

        $this->expectException(TypeError::class);
        $a->compare(10);
    }

    /**
     * Test compare() throws TypeError for string.
     */
    public function testCompareWithStringThrows(): void
    {
        $a = new Angle(10, 'deg');

        $this->expectException(TypeError::class);
        $a->compare('10deg');
    }

    /**
     * Test compare() throws TypeError for object.
     */
    public function testCompareWithObjectThrows(): void
    {
        $a = new Angle(10, 'deg');

        $this->expectException(TypeError::class);
        $a->compare(new stdClass());
    }

    /**
     * Test lessThan() method from Comparable trait.
     */
    public function testLessThan(): void
    {
        $a = new Angle(10, 'deg');
        $b = new Angle(20, 'deg');
        $c = new Angle(10, 'deg');

        $this->assertTrue($a->lessThan($b));
        $this->assertFalse($b->lessThan($a));
        $this->assertFalse($a->lessThan($c)); // Equal, not less than
    }

    /**
     * Test lessThanOrEqual() method from Comparable trait.
     */
    public function testLessThanOrEqual(): void
    {
        $a = new Angle(10, 'deg');
        $b = new Angle(20, 'deg');
        $c = new Angle(10, 'deg');

        $this->assertTrue($a->lessThanOrEqual($b));
        $this->assertTrue($a->lessThanOrEqual($c)); // Equal counts as <=
        $this->assertFalse($b->lessThanOrEqual($a));
    }

    /**
     * Test greaterThan() method from Comparable trait.
     */
    public function testGreaterThan(): void
    {
        $a = new Angle(10, 'deg');
        $b = new Angle(20, 'deg');
        $c = new Angle(10, 'deg');

        $this->assertTrue($b->greaterThan($a));
        $this->assertFalse($a->greaterThan($b));
        $this->assertFalse($a->greaterThan($c)); // Equal, not greater than
    }

    /**
     * Test greaterThanOrEqual() method from Comparable trait.
     */
    public function testGreaterThanOrEqual(): void
    {
        $a = new Angle(10, 'deg');
        $b = new Angle(20, 'deg');
        $c = new Angle(10, 'deg');

        $this->assertTrue($b->greaterThanOrEqual($a));
        $this->assertTrue($a->greaterThanOrEqual($c)); // Equal counts as >=
        $this->assertFalse($a->greaterThanOrEqual($b));
    }

    /**
     * Test comparison methods with negative angles.
     */
    public function testComparisonWithNegativeAngles(): void
    {
        $a = new Angle(-30, 'deg');
        $b = new Angle(-10, 'deg');
        $c = new Angle(10, 'deg');

        $this->assertTrue($a->lessThan($b));
        $this->assertTrue($b->lessThan($c));
        $this->assertTrue($c->greaterThan($a));
    }

    /**
     * Test comparison methods with wrapped vs unwrapped angles.
     */
    public function testComparisonRawVsWrapped(): void
    {
        $a = new Angle(10, 'deg');
        $b = new Angle(370, 'deg'); // 10° + 360°

        // Raw comparison: 370° > 10°
        $this->assertTrue($b->greaterThan($a));

        // After wrapping: both become 10° (unsigned)
        $aWrapped = new Angle(10, 'deg')->wrap();
        $bWrapped = new Angle(370, 'deg')->wrap();
        $this->assertTrue($aWrapped->equal($bWrapped));
    }

    /**
     * Test validUnits() returns expected units.
     */
    public function testValidUnits(): void
    {
        $units = Angle::getUnitConverter()->getUnitSymbols();

        // Check it's an array
        $this->assertIsArray($units);

        // Check expected units are present
        $expectedUnits = ['rad', 'deg', 'arcmin', 'arcsec', 'grad', 'turn'];
        foreach ($expectedUnits as $unit) {
            $this->assertContains($unit, $units);
        }
    }

    /**
     * Test getConversionFactor() with same unit returns 1.0.
     */
    public function testGetConversionFactorSameUnit(): void
    {
        $converter = Angle::getUnitConverter();
        $this->assertSame(1.0, $converter->convert(1, 'deg', 'deg'));
        $this->assertSame(1.0, $converter->convert(1, 'rad', 'rad'));
        $this->assertSame(1.0, $converter->convert(1, 'grad', 'grad'));
    }

    /**
     * Test getConversionFactor() for well-known exact conversions.
     */
    public function testGetConversionFactorDirectConversions(): void
    {
        $converter = Angle::getUnitConverter();

        // Degrees to arcminutes: 1 deg = 60 arcmin
        $this->assertEquals(60.0, $converter->convert(1, 'deg', 'arcmin'));

        // Degrees to arcseconds: 1 deg = 3600 arcsec
        $this->assertEquals(3600.0, $converter->convert(1, 'deg', 'arcsec'));

        // Arcminutes to arcseconds: 1 arcmin = 60 arcsec
        $this->assertEquals(60.0, $converter->convert(1, 'arcmin', 'arcsec'));

        // Turn to degrees: 1 turn = 360 deg
        $this->assertEquals(360.0, $converter->convert(1, 'turn', 'deg'));

        // Turn to gradians: 1 turn = 400 grad
        $this->assertEquals(400.0, $converter->convert(1, 'turn', 'grad'));

        // Degrees to gradians: 1 grad = 0.9 deg
        $this->assertEquals(0.9, $converter->convert(1, 'grad', 'deg'));
    }

    /**
     * Test getConversionFactor() reciprocal relationships.
     */
    public function testGetConversionFactorReciprocals(): void
    {
        $converter = Angle::getUnitConverter();

        // deg <-> arcmin
        $degToArcmin = $converter->convert(1, 'deg', 'arcmin');
        $arcminToDeg = $converter->convert(1, 'arcmin', 'deg');
        $this->assertFloatEquals(1.0, $degToArcmin * $arcminToDeg);

        // rad <-> deg
        $radToDeg = $converter->convert(1, 'rad', 'deg');
        $degToRad = $converter->convert(1, 'deg', 'rad');
        $this->assertFloatEquals(1.0, $radToDeg * $degToRad);

        // grad <-> turn
        $gradToTurn = $converter->convert(1, 'grad', 'turn');
        $turnToGrad = $converter->convert(1, 'turn', 'grad');
        $this->assertFloatEquals(1.0, $gradToTurn * $turnToGrad);
    }

    /**
     * Test getConversionFactor() throws ValueError for invalid units.
     */
    public function testGetConversionFactorInvalidFromUnit(): void
    {
        $this->expectException(ValueError::class);
        Angle::getUnitConverter()->convert(1, 'banana', 'deg');
    }

    /**
     * Test getConversionFactor() throws ValueError for invalid to unit.
     */
    public function testGetConversionFactorInvalidToUnit(): void
    {
        $this->expectException(ValueError::class);
        Angle::getUnitConverter()->convert(1, 'deg', 'banana');
    }

    /**
     * Test static convert() method for basic conversions.
     */
    public function testStaticConvert(): void
    {
        $converter = Angle::getUnitConverter();

        // 180 degrees = π radians
        $this->assertFloatEquals(M_PI, $converter->convert(180, 'deg', 'rad'));

        // π radians = 180 degrees
        $this->assertFloatEquals(180.0, $converter->convert(M_PI, 'rad', 'deg'));

        // 1 degree = 60 arcminutes
        $this->assertFloatEquals(60.0, $converter->convert(1, 'deg', 'arcmin'));

        // 90 degrees = 100 gradians
        $this->assertFloatEquals(100.0, $converter->convert(90, 'deg', 'grad'));

        // 0.5 turns = 180 degrees
        $this->assertFloatEquals(180.0, $converter->convert(0.5, 'turn', 'deg'));
    }

    /**
     * Test static convert() throws ValueError for invalid units.
     */
    public function testStaticConvertInvalidUnit(): void
    {
        $this->expectException(ValueError::class);
        Angle::getUnitConverter()->convert(90, 'banana', 'deg');
    }

    /**
     * Test to() method with various units.
     */
    public function testToMethod(): void
    {
        $angle = new Angle(90, 'deg');

        $this->assertFloatEquals(M_PI / 2, $angle->to('rad')->value);
        $this->assertFloatEquals(90.0, $angle->to('deg')->value);
        $this->assertFloatEquals(5400.0, $angle->to('arcmin')->value);
        $this->assertFloatEquals(324000.0, $angle->to('arcsec')->value);
        $this->assertFloatEquals(100.0, $angle->to('grad')->value);
        $this->assertFloatEquals(0.25, $angle->to('turn')->value);
    }

    /**
     * Test to() method with default parameter (radians).
     */
    public function testToMethodDefault(): void
    {
        $angle = new Angle(180, 'deg');
        $this->assertFloatEquals(M_PI, $angle->to('rad')->value);
    }

    /**
     * Test to() method throws ValueError for invalid unit.
     */
    public function testToMethodInvalidUnit(): void
    {
        $angle = new Angle(45, 'deg');
        $this->expectException(ValueError::class);
        $angle->to('banana');
    }
}
