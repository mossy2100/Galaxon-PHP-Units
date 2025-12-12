<?php

declare(strict_types=1);

namespace Galaxon\Units;

use Galaxon\Core\Integers;
use ValueError;

/**
 * Represents a decomposed unit symbol.
 *
 * A unit symbol like 'km2' is decomposed into:
 * - base: The unit without prefix or exponent ('m').
 * - prefix: The SI/binary prefix ('k').
 * - prefixMultiplier: The prefix multiplier (1000).
 * - exponent: The power (2).
 *
 * Computed properties:
 * - derived: The unit without prefix ('m2').
 * - prefixed: The full unit symbol ('km2').
 * - multiplier: The prefix multiplier raised to the exponent (1000² = 1e6).
 */
class Unit
{
    // region Properties

    /**
     * The base unit without prefix or exponent (e.g., 'm', 's').
     */
    private(set) string $base;

    /**
     * The SI/binary prefix symbol (e.g., 'k', 'm', 'G'), or empty string if none.
     */
    private(set) string $prefix = '';

    /**
     * The prefix multiplier (e.g., 1000 for kilo, 0.001 for milli).
     */
    private(set) int|float $prefixMultiplier;

    /**
     * The exponent (e.g., 2 for m², -1 for s⁻¹).
     */
    private(set) int $exponent = 1;

    // endregion

    // region Computed properties

    // PHP_CodeSniffer doesn't know about property hooks yet.
    // phpcs:disable PSR2.Classes.PropertyDeclaration
    // phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact

    /**
     * The derived unit symbol without prefix (e.g. 'm2', 's-1').
     */
    public string $derived {
        get {
            return $this->base . ($this->exponent === 1 ? '' : $this->exponent);
        }
    }

    /**
     * The full prefixed unit symbol (e.g. 'km2', 'ms-1').
     */
    public string $prefixed {
        get {
            return $this->prefix . $this->derived;
        }
    }

    /**
     * The prefix multiplier raised to the exponent (e.g., 1000² = 1e6 for km²).
     */
    public int|float $multiplier {
        get {
            return $this->prefixMultiplier ** $this->exponent;
        }
    }

    // phpcs:enable PSR2.Classes.PropertyDeclaration
    // phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact

    // endregion

    // region Constructor

    /**
     * Constructor.
     *
     * Parses the derived unit to extract the base unit and exponent.
     *
     * @param string $derived The unit without prefix (e.g., 'm2', 's-1').
     * @param string $prefix The prefix symbol (e.g., 'k', 'm', 'G'), or empty string if none.
     * @param int|float $prefixMultiplier The prefix multiplier (e.g., 1000 for kilo).
     * @throws ValueError If the derived unit format is invalid.
     */
    public function __construct(string $derived, string $prefix = '', int|float $prefixMultiplier = 1)
    {
        // Validate the unit string.
        $unitValid = preg_match('/^(\p{L}+)(-?\d+)?$/u', $derived, $matches);
        if (!$unitValid) {
            throw new ValueError(
                "Invalid unit $derived. A unit must comprise one or more letters optionally followed by an exponent."
            );
        }

        // Get the base unit.
        $base = $matches[1];

        // Get the exponent.
        if (!isset($matches[2]) || $matches[2] === '') {
            $exp = 1;
        } else {
            $exp = (int)$matches[2];

            // Validate the exponent.
            if ($exp < -9 || $exp === 0 || $exp === 1 || $exp > 9) {
                throw new ValueError("Invalid exponent $exp. Must be in the range -9 to 9 and not equal to 0 or 1.");
            }
        }

        // Set properties.
        $this->base = $base;
        $this->prefix = $prefix;
        $this->prefixMultiplier = $prefixMultiplier;
        $this->exponent = $exp;
    }

    // endregion

    // region Formatting

    /**
     * Format the unit as a string for display.
     *
     * Converts 'u' prefix to 'μ' for better display (e.g., 'um' → 'μm').
     * Exponents are converted to superscript (e.g., 'm2' → 'm²').
     *
     * @return string The formatted unit symbol.
     */
    public function __toString(): string
    {
        // Convert 'u' to 'μ' if necessary. Looks better.
        $prefix = $this->prefix === 'u' ? 'μ' : $this->prefix;

        // Get the exponent in superscript.
        $exp = $this->exponent === 1 ? '' : Integers::toSuperscript($this->exponent);

        // Construct the unit symbol.
        return $prefix . $this->base . $exp;
    }

    // endregion
}
