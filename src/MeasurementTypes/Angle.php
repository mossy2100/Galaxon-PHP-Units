<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use Galaxon\Core\Floats;
use Galaxon\Core\Numbers;
use Galaxon\Units\Measurement;
use Override;
use TypeError;
use ValueError;

class Angle extends Measurement
{
    // region Constants

    /**
     * Epsilons for comparisons.
     */
    public const float RAD_EPSILON = 1e-9;
    public const float TRIG_EPSILON = 1e-15;

    // endregion

    // region Factory methods

    /**
     * Checks that the input string, which is meant to indicate an angle, is valid.
     *
     * Different units (deg, rad, grad, turn) are supported, as used in CSS.
     * There can be zero or more spaces between the number and the unit.
     * @see https://developer.mozilla.org/en-US/docs/Web/CSS/angle
     *
     * Symbols for degrees, arcminutes, and arcseconds are also supported.
     * There cannot be any space between a number and its symbol, but it's ok to have spaces between parts.
     *
     * If valid, the angle is returned; otherwise, an exception is thrown.
     *
     * @param string $value The string to parse.
     * @return static A new Angle equivalent to the provided string.
     * @throws ValueError If the string does not represent a valid angle.
     */
    public static function parse(string $value): static
    {
        try {
            // Try to parse the angle using Measurement::parse().
            return parent::parse($value);
        } catch (ValueError $e) {
            // Check for a format containing symbols for degrees, arcminutes, and arcseconds.
            $rxNum = '\d+(?:\.\d+)?(?:[eE][+-]?\d+)?';
            $pattern = '/^(?:(?P<sign>[-+]?)\s*)?'
                       . "(?:(?P<deg>$rxNum)°\s*)?"
                       . "(?:(?P<min>$rxNum)[′']\s*)?"
                       . "(?:(?P<sec>$rxNum)[″\"])?$/u";
            if (preg_match($pattern, $value, $matches)) {
                // Require at least one component (deg/min/sec).
                if (empty($matches['deg']) && empty($matches['min']) && empty($matches['sec'])) {
                    throw $e;
                }

                // Get the sign.
                $sign = isset($matches['sign']) && $matches['sign'] === '-' ? -1 : 1;

                // Extract the parts (non-negative).
                $d = isset($matches['deg']) ? (float)$matches['deg'] : 0.0;
                $m = isset($matches['min']) ? (float)$matches['min'] : 0.0;
                $s = isset($matches['sec']) ? (float)$matches['sec'] : 0.0;

                // Convert to Angle.
                return static::fromParts($d, $m, $s, $sign);
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
     * @return array<string, int> Array of units with allowed prefixes flags.
     */
    #[Override]
    public static function getUnits(): array
    {
        return [
            'rad'    => self::PREFIX_CODE_SMALL_METRIC,  // radian
            'deg'    => 0,  // degree
            'arcmin' => 0,  // arcminute
            'arcsec' => 0,  // arcsecond
            'as'     => self::PREFIX_CODE_SMALL_METRIC,  // arcsecond (alias used with prefixes)
            'grad'   => 0,  // gradian
            'turn'   => 0,  // turn/revolution
        ];
    }

    /**
     * Get the conversions for Angle measurements.
     *
     * @return array<array{0: string, 1: string, 2: float, 3?: float}> Array of conversion definitions.
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            ['turn', 'rad', Floats::TAU],
            ['turn', 'deg', 360],
            ['deg', 'arcmin', 60],
            ['arcmin', 'arcsec', 60],
            ['arcsec', 'as', 1],
            ['turn', 'grad', 400],
        ];
    }

    // endregion

    // region Methods for working with angles in degrees, arcminutes, and arcseconds

    /**
     * Ordered list of angle units from largest (degrees) to smallest (arcseconds).
     * Used for parts decomposition and validation.
     *
     * @return array<int|string, string>
     */
    #[Override]
    public static function getPartUnits(): array
    {
        return ['deg' => '°', 'arcmin' => '′', 'arcsec' => '″'];
    }

    /**
     * Create an Angle as a sum of angles in different units.
     *
     * All parts must be non-negative.
     * If the Angle is negative, set the $sign parameter to -1.
     *
     * @param float $degrees The number of degrees.
     * @param float $arcmin The number of arcminutes.
     * @param float $arcsec The number of arcseconds.
     * @param int $sign -1 if the Angle is negative, 1 (or omitted) otherwise.
     * @return static A new Angle in degrees with a magnitude equal to the sum of the parts.
     * @throws TypeError If any of the values are not numbers.
     * @throws ValueError If any of the values are non-finite or negative.
     */
    public static function fromParts(float $degrees = 0, float $arcmin = 0, float $arcsec = 0, int $sign = 1): static
    {
        return self::fromPartsArray([
            'deg'    => $degrees,
            'arcmin' => $arcmin,
            'arcsec' => $arcsec,
            'sign'   => $sign
        ]);
    }

    /**
     * Format angle as component parts (degrees, arcminutes, arcseconds).
     *
     * Returns a string like "12° 34′ 56.789″".
     * Units other than the smallest unit are shown as integers.
     *
     * @param string $smallestUnit The smallest unit to include (default 'arcsec').
     * @param ?int $precision The number of decimal places for rounding the smallest unit, or null for no rounding.
     * @param bool $showZeros If true, show all components including zeros (default true for Angle/DMS notation).
     * @return string Formatted angle string.
     * @throws ValueError If any arguments are invalid.
     */
    #[Override]
    public function formatParts(string $smallestUnit = 'arcsec', ?int $precision = null, bool $showZeros = true): string
    {
        return parent::formatParts($smallestUnit, $precision, $showZeros);
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
        $radians = $this->to('rad')->value;
        return sin($radians);
    }

    /**
     * Cosine of the angle.
     *
     * @return float The cosine value.
     */
    public function cos(): float
    {
        $radians = $this->to('rad')->value;
        return cos($radians);
    }

    /**
     * Tangent of the angle.
     *
     * @return float The tangent value.
     */
    public function tan(): float
    {
        $radians = $this->to('rad')->value;
        $s = sin($radians);
        $c = cos($radians);

        // If cos is effectively zero, return ±INF (sign chosen by the side, i.e., sign of sine).
        // The built-in tan() function normally doesn't ever return ±INF.
        if (Floats::approxEqual($c, 0, 0, self::TRIG_EPSILON)) {
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
        $radians = $this->to('rad')->value;
        $c = cos($radians);

        // If cos is effectively zero, return ±INF.
        if (Floats::approxEqual($c, 0, 0, self::TRIG_EPSILON)) {
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
        $radians = $this->to('rad')->value;
        $s = sin($radians);

        // If sin is effectively zero, return ±INF.
        if (Floats::approxEqual($s, 0, 0, self::TRIG_EPSILON)) {
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
        $radians = $this->to('rad')->value;
        $s = sin($radians);
        $c = cos($radians);

        // If sin is effectively zero, return ±INF.
        if (Floats::approxEqual($s, 0, 0, self::TRIG_EPSILON)) {
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
        $radians = $this->to('rad')->value;
        return sinh($radians);
    }

    /**
     * Get the hyperbolic cosine of the angle.
     *
     * @return float The hyperbolic cosine value.
     */
    public function cosh(): float
    {
        $radians = $this->to('rad')->value;
        return cosh($radians);
    }

    /**
     * Get the hyperbolic tangent of the angle.
     *
     * @return float The hyperbolic tangent value.
     */
    public function tanh(): float
    {
        $radians = $this->to('rad')->value;
        return tanh($radians);
    }

    /**
     * Get the hyperbolic secant of the angle (1/cosh).
     *
     * @return float The hyperbolic secant value.
     */
    public function sech(): float
    {
        $radians = $this->to('rad')->value;
        return fdiv(1.0, cosh($radians));
    }

    /**
     * Get the hyperbolic cosecant of the angle (1/sinh).
     *
     * @return float The hyperbolic cosecant value.
     */
    public function csch(): float
    {
        $radians = $this->to('rad')->value;
        $sh = sinh($radians);

        // sinh(0) = 0, so return ±INF for values near zero.
        if (Floats::approxEqual($sh, 0, 0, self::TRIG_EPSILON)) {
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
        $radians = $this->to('rad')->value;
        $sh = sinh($radians);

        // sinh(0) = 0, so return ±INF for values near zero.
        if (Floats::approxEqual($sh, 0, 0, self::TRIG_EPSILON)) {
            return Numbers::copySign(INF, cosh($radians));
        }

        return fdiv(cosh($radians), $sh);
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
     * Check if this Angle is approximately equal to another.
     *
     * Overrides the parent method to use different default tolerances for comparing Angles.
     * For Angles, we want to compare the absolute difference in radians.
     *
     * @param mixed $other The value to compare with.
     * @param float $relTol The relative tolerance (default 0).
     * @param float $absTol The absolute tolerance (default 1e-9).
     * @return bool True if the values are equal, false otherwise (including for incompatible types).
     */
    public function approxEqual(mixed $other, float $relTol = 0, float $absTol = self::RAD_EPSILON): bool
    {
        // Check for incompatible types.
        if (!$other instanceof self) {
            return false;
        }

        // Convert both to radians.
        $thisRad = $this->unit === 'rad' ? $this->value : $this->to('rad')->value;
        $otherRad = $other->unit === 'rad' ? $other->value : $other->to('rad')->value;

        // Compare the values.
        return Floats::approxEqual($thisRad, $otherRad, $relTol, $absTol);
    }

    // endregion
}
