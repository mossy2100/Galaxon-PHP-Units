<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Core\Floats;
use Galaxon\Core\Numbers;
use Galaxon\Units\Measurement;
use Galaxon\Units\TypeError;
use Override;
use ValueError;

class Angle extends Measurement
{
    // region Constants

    // Epsilons for comparisons.
    public const float RAD_EPSILON = 1e-9;
    public const float TRIG_EPSILON = 1e-12;

    // Constants for use as smallest unit arguments.
    public const int UNIT_DEGREE = 0;
    public const int UNIT_ARCMINUTE = 1;
    public const int UNIT_ARCSECOND = 2;

    // endregion

    // region Factory methods

    /**
     * Checks that the input string, which is meant to indicate an angle, is valid.
     *
     * Different units (deg, rad, grad, turn) are supported, as used in CSS.
     * There can be spaces between the number and the unit.
     * @see https://developer.mozilla.org/en-US/docs/Web/CSS/angle
     *
     * Symbols for degrees, arcminutes, and arcseconds are also supported.
     * There cannot be any space between a number and its unit, but it's ok to have a single space
     * between two parts.
     *
     * If valid, the angle is returned; otherwise, an exception is thrown.
     *
     * @param string $value The string to parse.
     * @return static A new angle equivalent to the provided string.
     * @throws ValueError If the string does not represent a valid angle.
     */
    public static function parse(string $value): static
    {
        try {
            // Try to parse the angle using Measurement::parse().
            return parent::parse($value);
        } catch (ValueError $e) {
            // Check for a format containing symbols for degrees, arcminutes, and arcseconds.
            $num = '\d+(?:\.\d+)?(?:[eE][+-]?\d+)?';
            $pattern = "/^(?:(?P<sign>[-+]?)\s*)?"
                       . "(?:(?P<deg>$num)°\s*)?"
                       . "(?:(?P<min>$num)[′']\s*)?"
                       . "(?:(?P<sec>$num)[″\"])?$/u";
            if (preg_match($pattern, $value, $matches)) {
                // Require at least one component (deg/min/sec).
                if (empty($matches['deg']) && empty($matches['min']) && empty($matches['sec'])) {
                    throw $e;
                }

                // Get the sign.
                $sign = isset($matches['sign']) && $matches['sign'] === '-' ? -1 : 1;

                // Extract the parts.
                $d = isset($matches['deg']) ? $sign * (float)$matches['deg'] : 0.0;
                $m = isset($matches['min']) ? $sign * (float)$matches['min'] : 0.0;
                $s = isset($matches['sec']) ? $sign * (float)$matches['sec'] : 0.0;

                // Convert to angle.
                return self::fromDMS($d, $m, $s);
            }

            // Invalid format.
            throw $e;
        }
    }

    // endregion

    // region Measurement methods

    /**
     * Get the units for Angle measurements.
     *
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            'rad' => true,    // radian (accepts metric prefixes)
            'deg' => false,   // degree
            'arcmin' => false, // arcminute
            'arcsec' => false, // arcsecond
            'grad' => false,  // gradian
            'turn' => false,  // turn/revolution
        ];
    }

    /**
     * Get the conversions for Angle measurements.
     *
     * @return array<array<string, string, int|float>>
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            ['turn', 'rad', Floats::TAU],
            ['turn', 'deg', 360],
            ['deg', 'arcmin', 60],
            ['arcmin', 'arcsec', 60],
            ['turn', 'grad', 400],
        ];
    }

    // endregion

    // region Methods for working with angles in degrees, arcminutes, and arcseconds

    /**
     * Create an angle from degrees, arcminutes, and arcseconds.
     *
     * NB: In theory all parts SHOULD be either non-negative (i.e. 0 or positive) or non-positive (i.e. 0 or negative).
     * However, this is not enforced. Neither do any of the values have to be within a certain range (e.g. 0-60 for
     * arcminutes or arcseconds).
     * You'll usually want to use the same sign for all parts.
     *
     * @param float $degrees The degrees part.
     * @param float $arcmin The arcminutes part (optional).
     * @param float $arcsec The arcseconds part (optional).
     * @return self A new angle with a magnitude equal to the provided angle.
     * @throws ValueError If any of the arguments are non-finite numbers.
     * @example
     * If you want to convert -12° 34′ 56″ to degrees, call fromDMS(-12, -34, -56)
     * If you want to convert -12° 56″ to degrees, call fromDMS(-12, 0, -56).
     */
    public static function fromDMS(float $degrees, float $arcmin = 0.0, float $arcsec = 0.0): self
    {
        // Compute the total degrees.
        // If any of the arguments are non-finite, the constructor will throw a ValueError.
        return new self($degrees + $arcmin / 60 + $arcsec / 3600, 'deg');
    }

    /**
     * Get the angle in degrees, arcminutes, and arcseconds.
     * The result will be an array with 1-3 values, depending on the requested smallest unit.
     * Only the last item may have a fractional part; others will be whole numbers.
     *
     * If the angle is positive, the resulting values will all be positive.
     * If the angle is zero, the resulting values will all be zero.
     * If the angle is negative, the resulting values will all be negative.
     *
     * For the $smallestUnit parameter, you can use the UNIT_* class constants, i.e.
     * - UNIT_DEGREE for degrees only
     * - UNIT_ARCMINUTE for degrees and arcminutes
     * - UNIT_ARCSECOND for degrees, arcminutes, and arcseconds
     *
     * @param int $smallestUnit 0 for degree, 1 for arcminute, 2 for arcsecond (default).
     * @return float[] An array of 1-3 floats with the degrees, arcminutes, and arcseconds.
     * @throws ValueError If $smallestUnit is not 0, 1, or 2.
     */
    public function toDMS(int $smallestUnit = self::UNIT_ARCSECOND): array
    {
        $angleInDegrees = $this->to('deg');
        $sign = Numbers::sign($angleInDegrees->value, false);
        $totalDeg = abs($angleInDegrees->value);

        switch ($smallestUnit) {
            case self::UNIT_DEGREE:
                $d = $totalDeg;

                // Apply sign and normalize -0.0 to 0.0.
                $d = Floats::normalizeZero($d * $sign);

                return [$d];

            case self::UNIT_ARCMINUTE:
                // Convert the total degrees to degrees and minutes (non-negative).
                $d = floor($totalDeg);
                $m = ($totalDeg - $d) * 60;

                // Apply sign and normalize -0.0 to 0.0.
                $d = Floats::normalizeZero($d * $sign);
                $m = Floats::normalizeZero($m * $sign);

                return [$d, $m];

            case self::UNIT_ARCSECOND:
                // Convert the total degrees to degrees, minutes, and seconds (non-negative).
                $d = floor($totalDeg);
                $fMin = ($totalDeg - $d) * 60;
                $m = floor($fMin);
                $s = ($fMin - $m) * 60;

                // Apply sign and normalize -0.0 to 0.0.
                $d = Floats::normalizeZero($d * $sign);
                $m = Floats::normalizeZero($m * $sign);
                $s = Floats::normalizeZero($s * $sign);

                return [$d, $m, $s];

            default:
                throw new ValueError(
                    'The smallest unit must be 0 for degree, 1 for arcminute, or 2 for arcsecond (default).'
                );
        }
    }

    /**
     * Format a given angle with degrees symbol, plus optional arcminutes and arcseconds.
     *
     * @param int $smallestUnit 0 for degrees, 1 for arcminutes, 2 for arcseconds (default).
     * @param ?int $precision Optional number of decimal places for the smallest unit.
     * @return string The degrees, arcminutes, and arcseconds nicely formatted as a string.
     * @throws ValueError If the smallest unit argument is not 0, 1, or 2.
     * @example
     * $alpha = Angle::fromDegrees(12.3456789);
     * echo $alpha->formatDMS(UNIT_DEGREE);    // 12.3456789°
     * echo $alpha->formatDMS(UNIT_ARCMINUTE); // 12° 20.740734′
     * echo $alpha->formatDMS(UNIT_ARCSECOND); // 12° 20′ 44.44404″
     *
     * For the $smallestUnit parameter, you can use the UNIT class constants, i.e.
     * - UNIT_DEGREE for degrees only
     * - UNIT_ARCMINUTE for degrees and arcminutes
     * - UNIT_ARCSECOND for degrees, arcminutes, and arcseconds
     */
    public function formatDMS(int $smallestUnit = self::UNIT_ARCSECOND, ?int $precision = null): string
    {
        // Get the sign string.
        $sign = $this->value < 0 ? '-' : '';

        // Convert to degrees, with optional arcminutes and/or arcseconds.
        $parts = $this->abs()->toDMS($smallestUnit);

        switch ($smallestUnit) {
            case self::UNIT_DEGREE:
                [$d] = $parts;
                $strDeg = self::formatValue($d, 'f', $precision);
                return "$sign{$strDeg}°";

            case self::UNIT_ARCMINUTE:
                [$d, $m] = $parts;

                // Round the smallest unit if requested.
                if ($precision !== null) {
                    $m = round($m, $precision);

                    // Handle floating-point drift and carry.
                    if ($m >= 60) {
                        $m = 0.0;
                        $d += 1.0;
                    }
                }

                $strMin = self::formatValue($m, 'f', $precision);
                return "$sign{$d}° {$strMin}′";

            case self::UNIT_ARCSECOND:
                [$d, $m, $s] = $parts;

                // Round the smallest unit if requested.
                if ($precision !== null) {
                    $s = round($s, $precision);

                    // Handle floating-point drift and carry.
                    if ($s >= 60) {
                        $s = 0.0;
                        $m += 1.0;
                    }
                    if ($m >= 60) {
                        $m = 0.0;
                        $d += 1.0;
                    }
                }

                $strSec = self::formatValue($s, 'f', $precision);
                return "$sign{$d}° {$m}′ {$strSec}″";

            // @codeCoverageIgnoreStart
            default:
                throw new ValueError(
                    'The smallest unit must be 0 for degree, 1 for arcminute, or 2 for arcsecond (default).'
                );
            // @codeCoverageIgnoreEnd
        }
    }

    // endregion

    // region Trigonometric methods

    /**
     * Sine of the angle.
     *
     * @return float The sine value.
     */
    public function sin(): float
    {
        return sin($this->value);
    }

    /**
     * Cosine of the angle.
     *
     * @return float The cosine value.
     */
    public function cos(): float
    {
        return cos($this->value);
    }

    /**
     * Tangent of the angle.
     *
     * @return float The tangent value.
     */
    public function tan(): float
    {
        $s = sin($this->value);
        $c = cos($this->value);

        // If cos is effectively zero, return ±INF (sign chosen by the side, i.e., sign of sine).
        // The built-in tan() function normally doesn't ever return ±INF.
        if (Floats::approxEqual($c, 0, self::TRIG_EPSILON)) {
            return Numbers::copySign(INF, $s);
        }

        // Otherwise do IEEE‑754 division (no warnings/exceptions).
        return fdiv($s, $c);
    }

    /**
     * Secant of the angle (1/cos).
     *
     * @return float The secant value.
     */
    public function sec(): float
    {
        $c = cos($this->value);

        // If cos is effectively zero, return ±INF.
        if (Floats::approxEqual($c, 0, self::TRIG_EPSILON)) {
            return Numbers::copySign(INF, $c);
        }

        return fdiv(1.0, $c);
    }

    /**
     * Cosecant of the angle (1/sin).
     *
     * @return float The cosecant value.
     */
    public function csc(): float
    {
        $s = sin($this->value);

        // If sin is effectively zero, return ±INF.
        if (Floats::approxEqual($s, 0, self::TRIG_EPSILON)) {
            return Numbers::copySign(INF, $s);
        }

        return fdiv(1.0, $s);
    }

    /**
     * Cotangent of the angle (cos/sin).
     *
     * @return float The cotangent value.
     */
    public function cot(): float
    {
        $s = sin($this->value);
        $c = cos($this->value);

        // If sin is effectively zero, return ±INF.
        if (Floats::approxEqual($s, 0, self::TRIG_EPSILON)) {
            return Numbers::copySign(INF, $c);
        }

        return fdiv($c, $s);
    }

    // endregion

    // region Hyperbolic methods

    /**
     * Get the hyperbolic sine of the angle.
     *
     * @return float The hyperbolic sine value.
     */
    public function sinh(): float
    {
        return sinh($this->value);
    }

    /**
     * Get the hyperbolic cosine of the angle.
     *
     * @return float The hyperbolic cosine value.
     */
    public function cosh(): float
    {
        return cosh($this->value);
    }

    /**
     * Get the hyperbolic tangent of the angle.
     *
     * @return float The hyperbolic tangent value.
     */
    public function tanh(): float
    {
        return tanh($this->value);
    }

    /**
     * Get the hyperbolic secant of the angle (1/cosh).
     *
     * @return float The hyperbolic secant value.
     */
    public function sech(): float
    {
        return fdiv(1.0, cosh($this->value));
    }

    /**
     * Get the hyperbolic cosecant of the angle (1/sinh).
     *
     * @return float The hyperbolic cosecant value.
     */
    public function csch(): float
    {
        $sh = sinh($this->value);

        // sinh(0) = 0, so return ±INF for values near zero.
        if (Floats::approxEqual($sh, 0, self::TRIG_EPSILON)) {
            return Numbers::copySign(INF, $sh);
        }

        return fdiv(1.0, $sh);
    }

    /**
     * Get the hyperbolic cotangent of the angle (cosh/sinh).
     *
     * @return float The hyperbolic cotangent value.
     */
    public function coth(): float
    {
        $sh = sinh($this->value);

        // sinh(0) = 0, so return ±INF for values near zero.
        if (Floats::approxEqual($sh, 0, self::TRIG_EPSILON)) {
            return Numbers::copySign(INF, cosh($this->value));
        }

        return fdiv(cosh($this->value), $sh);
    }

    // endregion

    // region Instance methods

    /**
     * Normalize an angle to a standard range.
     *
     * The range of values varies depending on the $unitsPerTurn parameter *and* the $signed flag.
     *
     * If $signed is true (default), the range is (-$unitsPerTurn/2, $unitsPerTurn/2]
     * This means the minimum value is *excluded* in the range, while the maximum value is *included*.
     * For radians, this is (-π, π]
     * For degrees, this is (-180, 180]
     *
     * If $signed is false, the range is [0, $unitsPerTurn)
     * This means the minimum value is *included* in the range, while the maximum value is *excluded*.
     * For radians, this is [0, τ)
     * For degrees, this is [0, 360)
     *
     * @see https://en.wikipedia.org/wiki/Principal_value#Complex_argument
     *
     * @param bool $signed If true, wrap to the signed range; otherwise wrap to the unsigned range.
     * @return self A new angle with the wrapped value.
     *
     * @example
     * $alpha = new Angle(270, 'deg');
     * $wrapped = $alpha->wrap(); // now $wrapped->value == -90
     */
    public function wrap(bool $signed = true): self
    {
        // Get the units per turn for the current unit.
        $unitsPerTurn = new self(1, 'turn')->to($this->unit)->value;

        // Reduce using fmod to avoid large magnitudes.
        // $r will be in the range [0, $unitsPerTurn) if $value is positive, or (-$unitsPerTurn, 0] if negative.
        $r = fmod($this->value, $unitsPerTurn);

        // Adjust to fit within range bounds.
        // The value may be outside the range due to the sign of $value or the value of $signed.
        if ($signed) {
            // Signed range is (-$half, $half]
            $half = $unitsPerTurn / 2.0;
            if ($r <= -$half) {
                $r += $unitsPerTurn;
            } elseif ($r > $half) {
                $r -= $unitsPerTurn;
            }
        } else {
            // Unsigned range is [0, $unitsPerTurn)
            if ($r < 0.0) {
                $r += $unitsPerTurn;
            }
        }

        // Canonicalize -0.0 to 0.0.
        $r = Floats::normalizeZero($r);

        // Return a new Angle with the wrapped value.
        return new self($r, $this->unit);
    }

    /**
     * Override Compareable::compare() with an epsilon more suitable for comparing values in radians.
     *
     * @param mixed $other The other value to compare to.
     * @param float $epsilon The maximum absolute difference between the values for them to be considered equal.
     * @return int -1 if this value is less than $other, 0 if they are equal, or 1 if this value is greater than $other.
     * @throws TypeError If $other is not an Angle.
     */
    #[Override]
    public function compare(mixed $other, float $epsilon = self::RAD_EPSILON): int {
        return parent::compare($other, $epsilon);
    }

    // endregion
}
