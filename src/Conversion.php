<?php

declare(strict_types=1);

namespace Galaxon\Units;

use Galaxon\Core\Numbers;
use Stringable;
use ValueError;

/**
 * Represents an affine transformation for unit conversion.
 *
 * Implements the conversion formula: y = m*x + k
 * where:
 * - m is the multiplier (scale factor)
 * - k is the offset (additive constant, used for temperature conversions)
 * - x is the input value in the initial unit
 * - y is the output value in the final unit
 *
 * Error scores are tracked through all operations to enable finding optimal
 * conversion paths in the unit conversion graph.
 */
class Conversion implements Stringable
{
    // region Properties

    /**
     * The initial unit (source).
     *
     * @var string
     */
    public readonly string $initialUnit;

    /**
     * The final unit (destination).
     *
     * @var string
     */
    public readonly string $finalUnit;

    /**
     * The scale factor (cannot be zero).
     *
     * @var FloatWithError
     */
    public readonly FloatWithError $multiplier;

    /**
     * The additive offset (default 0).
     *
     * Typically zero except for affine conversions like temperature scales.
     *
     * @var FloatWithError
     */
    public readonly FloatWithError $offset;

    // PHPCS doesn't know property hooks yet.
    // phpcs:disable PSR2.Classes.PropertyDeclaration
    // phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact

    /**
     * The error score for this conversion.
     *
     * A heuristic metric for comparing conversion quality and finding optimal paths.
     * Computed as the sum of absolute errors from multiplier and offset, assuming
     * a representative input value of 1 for comparison purposes. Lower scores
     * indicate more accurate conversions.
     *
     * @var float
     */
    public float $errorScore {
        get => $this->multiplier->absoluteError + $this->offset->absoluteError;
    }

    // phpcs:enable PSR2.Classes.PropertyDeclaration
    // phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact

    // endregion

    // region Constructor

    /**
     * Constructor.
     *
     * @param string $initialUnit The initial unit (source).
     * @param string $finalUnit The final unit (destination).
     * @param int|float|FloatWithError $multiplier The scale factor (cannot be 0).
     * @param int|float|FloatWithError $offset The additive offset (default 0).
     * @throws ValueError If the multiplier is zero.
     */
    public function __construct(
        string $initialUnit,
        string $finalUnit,
        int|float|FloatWithError $multiplier,
        int|float|FloatWithError $offset = 0
    ) {
        // Ensure multiplier is a FloatWithError.
        if (!$multiplier instanceof FloatWithError) {
            $multiplier = new FloatWithError($multiplier);
        }

        // Ensure multiplier is not zero.
        if ($multiplier->value === 0.0) {
            throw new ValueError('Multiplier cannot be zero.');
        }

        // Ensure offset is a FloatWithError.
        if (!$offset instanceof FloatWithError) {
            $offset = new FloatWithError($offset);
        }

        // Set the properties.
        $this->initialUnit = $initialUnit;
        $this->finalUnit = $finalUnit;
        $this->multiplier = $multiplier;
        $this->offset = $offset;
    }

    // endregion

    // region Apply a conversion

    /**
     * Apply conversion to an input value.
     *
     * @param int|float|FloatWithError $value The input value.
     * @return FloatWithError The result of the conversion.
     */
    public function apply(int|float|FloatWithError $value): FloatWithError
    {
        // Convert the value. y = mx + k
        return $this->multiplier->mul($value)->add($this->offset);
    }

    // endregion

    // region Methods to transform conversions into new ones

    /**
     * Invert this conversion to go from final unit back to initial unit.
     *
     * Given: b = a * m1 + k1
     * Solve for a: a = b * (1/m1) + (-k1/m1)
     *
     * @return self The inverted conversion (final->initial).
     */
    public function invert(): self
    {
        $m1 = $this->multiplier;
        $k1 = $this->offset;

        // m = 1 / m1
        $m = $m1->inv();
        // k = -k1 / m1
        $k = $k1->neg()->div($m1);
        // Swap the units when inverting.
        return new self($this->finalUnit, $this->initialUnit, $m, $k);
    }

    /**
     * Compose two conversions sequentially: initial->common and common->final.
     *
     * Given:
     *   b = a * m1 + k1  (this conversion)
     *   c = b * m2 + k2  (other conversion)
     * Result: c = a * (m1 * m2) + (k1 * m2 + k2)
     *
     * @param self $other The second conversion (common->final).
     * @return self The combined conversion (initial->final).
     */
    public function combineSequential(self $other): self
    {
        $m1 = $this->multiplier;
        $k1 = $this->offset;
        $m2 = $other->multiplier;
        $k2 = $other->offset;

        // m = m1 * m2
        $m = $m1->mul($m2);
        // k = k1 * m2 + k2
        $k = $k1->mul($m2)->add($k2);
        // Result is initial->final.
        return new self($this->initialUnit, $other->finalUnit, $m, $k);
    }

    /**
     * Compose two conversions convergently: initial->common and final->common.
     *
     * Both conversions point toward the common unit.
     *
     * Given:
     *   b = a * m1 + k1  (this conversion: initial->common)
     *   b = c * m2 + k2  (other conversion: final->common)
     * Result: c = a * (m1 / m2) + ((k1 - k2) / m2)
     *
     * @param self $other The second conversion (final->common).
     * @return self The combined conversion (initial->final).
     */
    public function combineConvergent(self $other): self
    {
        $m1 = $this->multiplier;
        $k1 = $this->offset;
        $m2 = $other->multiplier;
        $k2 = $other->offset;

        // m = m1 / m2
        $m = $m1->div($m2);
        // k = (k1 - k2) / m2
        $k = ($k1->sub($k2))->div($m2);
        // Result is initial->final.
        return new self($this->initialUnit, $other->initialUnit, $m, $k);
    }

    /**
     * Compose two conversions divergently: common->initial and common->final.
     *
     * Both conversions point away from the common unit.
     *
     * Given:
     *   a = b * m1 + k1  (this conversion: common->initial)
     *   c = b * m2 + k2  (other conversion: common->final)
     * Result: c = a * (m2 / m1) + (k2 - (k1 * m2 / m1))
     *
     * @param self $other The second conversion (common->final).
     * @return self The combined conversion (initial->final).
     */
    public function combineDivergent(self $other): self
    {
        $m1 = $this->multiplier;
        $k1 = $this->offset;
        $m2 = $other->multiplier;
        $k2 = $other->offset;

        // m = m2 / m1
        $m = $m2->div($m1);
        // k = k2 - (k1 * m2 / m1)
        //   = k2 - (k1 * m)
        $k = $k2->sub($k1->mul($m));
        // Result is initial->final.
        return new self($this->finalUnit, $other->finalUnit, $m, $k);
    }

    /**
     * Compose two conversions oppositely: common->initial and final->common.
     *
     * Conversions flow in opposite directions through the common unit.
     *
     * Given:
     *   a = b * m1 + k1  (this conversion: common->initial)
     *   b = c * m2 + k2  (other conversion: final->common)
     * Result: c = a / (m1 * m2) + ((-k2 - (k1 / m1)) / m2)
     *
     * @param self $other The second conversion (final->common).
     * @return self The combined conversion (initial->final).
     */
    public function combineOpposite(self $other): self
    {
        $m1 = $this->multiplier;
        $k1 = $this->offset;
        $m2 = $other->multiplier;
        $k2 = $other->offset;

        // m = 1 / (m1 * m2)
        $m = $m1->mul($m2)->inv();
        // k = (-k2 - (k1 / m1)) / m2
        $k = $k2->neg()->sub($k1->div($m1))->div($m2);
        // Result is initial->final.
        return new self($this->finalUnit, $other->initialUnit, $m, $k);
    }

    // endregion

    // region Stringable implementation

    /**
     * Convert this conversion to a string representation.
     *
     * Format: "finalUnit = initialUnit * multiplier + offset (error score: X)"
     * Omits multiplier if 1, omits offset if 0.
     *
     * @return string The string representation of this conversion.
     */
    public function __toString(): string
    {
        $str = "$this->finalUnit = $this->initialUnit";
        if (!Numbers::equal($this->multiplier->value, 1)) {
            $str .= " * {$this->multiplier->value}";
        }
        if (!Numbers::equal($this->offset->value, 0)) {
            $sign = $this->offset->value < 0 ? '-' : '+';
            $str .= " $sign " . abs($this->offset->value);
        }
        $str .= " (error score: $this->errorScore)";
        return $str;
    }

    // endregion
}
