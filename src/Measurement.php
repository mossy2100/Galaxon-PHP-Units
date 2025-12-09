<?php

declare(strict_types=1);

namespace Galaxon\Units;

use DivisionByZeroError;
use Galaxon\Core\Arrays;
use Galaxon\Core\Floats;
use Galaxon\Core\Numbers;
use Galaxon\Core\Traits\Comparable;
use Galaxon\Core\Types;
use LogicException;
use Override;
use ReflectionClass;
use Stringable;
use TypeError;
use ValueError;

/**
 * Abstract base class for physical measurements with units.
 *
 * Provides a framework for creating strongly-typed measurement classes (Length, Mass, Time, etc.)
 * with automatic unit conversion, arithmetic operations, and comparison capabilities.
 *
 * Derived classes must implement:
 * - getUnits(): Define the base units and specify which prefix sets they accept
 * - Optionally override getConversions(): Define conversion factors between units
 *
 * Prefix system:
 * - Units can specify allowed prefixes using bitwise flags (PREFIXES_METRIC, PREFIXES_BINARY, etc.)
 * - Provides fine-grained control (e.g., radian can accept only small metric prefixes)
 * - Supports combinations (e.g., byte can accept both metric and binary prefixes)
 *
 * Features:
 * - Automatic validation of units and values
 * - Lazy initialization of UnitConverter for each measurement type
 * - Type-safe arithmetic operations (add, subtract, multiply, divide)
 * - Comparison and equality testing with epsilon tolerance
 * - Flexible string formatting and parsing
 */
abstract class Measurement implements Stringable
{
    use Comparable;

    // region Instance properties

    /**
     * The numeric value of the measurement in the specified unit.
     *
     * @var float
     */
    public readonly float $value;

    /**
     * The unit of the measurement (e.g., 'kg', 'mm', 'h', 'm3').
     *
     * @var string
     */
    public readonly string $unit;

    // endregion

    // region Static properties

    /**
     * Cache of UnitConverter instances, one per derived Measurement class.
     *
     * Indexed by fully-qualified class name.
     *
     * @var array<class-string, UnitConverter>
     */
    private static array $unitConverters = [];

    // endregion

    // region Constants

    /**
     * Constants for prefix set codes.
     */
    public const int PREFIXES_SMALL_METRIC = 1;
    public const int PREFIXES_LARGE_METRIC = 2;
    public const int PREFIXES_BINARY = 4;
    public const int PREFIXES_METRIC = self::PREFIXES_SMALL_METRIC | self::PREFIXES_LARGE_METRIC;
    public const int PREFIXES_LARGE = self::PREFIXES_LARGE_METRIC | self::PREFIXES_BINARY;
    public const int PREFIXES_ALL = self::PREFIXES_METRIC | self::PREFIXES_BINARY;

    // endregion

    // region Constructor

    /**
     * Constructor.
     *
     * Creates a new measurement with the specified value and unit.
     * Validates that the value is finite and the unit is valid for this measurement type.
     *
     * The constructor is final because it's often called as "new static" to instantiate a derived class.
     * In PHP, when overriding the constructor in a derived class, the signature may be altered.
     * We want to prevent that so the "new static" expressions won't break.
     * If you want to override the constructor, create a factory method instead.
     *
     * @param int|float $value The numeric value in the given unit.
     * @param string $unit The unit (e.g., 'kg', 'mm', 'hr').
     * @throws ValueError If the value is non-finite (±∞ or NaN) or if the unit is invalid.
     * @throws LogicException If the derived class is not properly configured.
     */
    final public function __construct(int|float $value, string $unit)
    {
        // Check the value is finite.
        if (!is_finite($value)) {
            throw new ValueError('Measurement value cannot be ±∞ or NaN.');
        }

        // Ensure the UnitConverter has been validated and created.
        $unitConverter = static::getUnitConverter();

        // Check the unit is valid.
        $unitConverter->checkUnitIsValid($unit);

        // Set the properties.
        $this->value = $value;
        $this->unit = $unit;
    }

    // endregion

    // region Factory methods

    /**
     * Parse a string representation into a Measurement object.
     *
     * Accepts formats like "123.45 km", "90deg", "1.5e3 ms".
     * Whitespace between value and unit is optional.
     *
     * @param string $value The string to parse.
     * @return static A new Measurement parsed from the string.
     * @throws ValueError If the string format is invalid.
     *
     * @example
     *   Length::parse("123.45 km")  // Length(123.45, 'km')
     *   Angle::parse("90deg")       // Angle(90.0, 'deg')
     *   Time::parse("1.5e3 ms")     // Time(1500.0, 'ms')
     */
    public static function parse(string $value): Measurement
    {
        // Prepare an error message with the original value.
        $class = new ReflectionClass(static::class)->getShortName();
        $err = "The provided string '$value' does not represent a valid $class.";

        // Reject empty input.
        $value = trim($value);
        if ($value === '') {
            throw new ValueError($err);
        }

        // Look for <num><unit>.
        // Whitespace between the number and unit is permitted, and the unit must be valid (case-sensitive).
        $num = '[-+]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?';
        $units = implode('|', static::getUnitConverter()->getValidUnits());
        if (preg_match("/^($num)\s*($units)$/", $value, $m)) {
            return new static((float)$m[1], $m[2]);
        }

        // Invalid format.
        throw new ValueError($err);
    }

    /**
     * Try to parse a string representation into a Measurement object of the calling class.
     *
     * @param string $value The string to parse.
     * @return ?static A new Measurement parsed from the string, or null if parsing failed.
     */
    public static function tryParse(string $value): ?static
    {
        try {
            return static::parse($value);
        } catch (ValueError) {
            return null;
        }
    }

    // endregion

    // region Instance methods

    /**
     * Convert this measurement to a different unit.
     *
     * Returns a new Measurement object with the equivalent value in the target unit.
     *
     * @param string $unit The target unit to convert to.
     * @return static A new Measurement in the specified unit.
     * @throws ValueError If the target unit is invalid.
     * @throws LogicException If no conversion path exists between the units.
     *
     * @example
     *   $length = new Length(1000, 'm');
     *   $km = $length->to('km');  // Length(1, 'km')
     */
    public function to(string $unit): static
    {
        // Convert the value to the target unit.
        $value = static::getUnitConverter()->convert($this->value, $this->unit, $unit);

        // Return the new Measurement.
        return new static($value, $unit);
    }

    /**
     * Format the measurement as a string with control over precision and notation.
     *
     * @param string $specifier Format type: 'f' (fixed), 'e'/'E' (scientific), 'g'/'G' (shortest).
     * @param ?int $precision Number of digits (meaning depends on specifier).
     * @param bool $trimZeros If true, remove trailing zeros and decimal point.
     * @param bool $includeSpace If true, insert space between value and unit.
     * @return string The formatted measurement string.
     * @throws ValueError If specifier or precision are invalid.
     *
     * @example
     *   $angle->format('f', 2)       // "90.00 deg"
     *   $angle->format('e', 3)       // "1.571e+0 rad"
     *   $angle->format('f', 0, true, false)  // "90deg"
     *
     * @see static::formatValue() For details on format specifiers.
     */
    public function format(
        string $specifier = 'f',
        ?int $precision = null,
        bool $trimZeros = true,
        bool $includeSpace = true
    ): string {
        // Return the formatted string. Arguments will be validated in formatValue().
        return static::formatValue($this->value, $specifier, $precision, $trimZeros)
               . ($includeSpace ? ' ' : '') . static::formatUnit($this->unit);
    }

    /**
     * Convert the measurement to a string using default formatting.
     *
     * Uses PHP's default float-to-string conversion with normalized zero.
     * For custom formatting, use format() instead.
     *
     * @return string The measurement as a string (e.g., "1.5707963267949 rad").
     */
    #[Override]
    public function __toString(): string
    {
        return Floats::normalizeZero($this->value) . ' ' . static::formatUnit($this->unit);
    }

    // endregion

    // region Comparison methods

    /**
     * Check the $this and $other objects have the same type, and get the value of the $other Measurement in the same
     * unit as the $this one. Return the value.
     *
     * @param self $other Measurement The other measurement to compare with.
     * @return float|int The value of the other measurement in the same unit as this one.
     * @throws LogicException If no conversion path exists between the units.
     * @throws TypeError If the other Measurement has a different type.
     */
    private function preCompare(self $other)
    {
        // Check the two measurements have the same types.
        if (!Types::haveSameType($this, $other)) {
            throw new TypeError('The two measurements being compared must be of the same type.');
        }

        // Get the other Measurement in the same unit as this one.
        /** @var Measurement $other */
        return $this->unit === $other->unit ? $other->value : $other->to($this->unit)->value;
    }

    /**
     * Compare two Measurements.
     *
     * This method will only return 0 for *exactly* equal.
     * It's usually preferable to use approxCompare() instead, which allows for user-defined tolerances.
     *
     * Automatically converts the other measurement to this one's unit before comparing.
     *
     * @param mixed $other The measurement to compare with.
     * @return int -1 if this < other, 0 if equal, 1 if this > other.
     * @throws TypeError If the other Measurement has a different type.
     * @throws LogicException If no conversion path exists between the units.
     */
    public function compare(mixed $other): int
    {
        $otherValue = $this->preCompare($other);
        return Numbers::sign($this->value <=> $otherValue);
    }

    /**
     * Compare two Measurements.
     *
     * This method returns 0 for *approximately* equal, i.e. within the given tolerances.
     * Automatically converts the other measurement to this one's unit before comparing.
     *
     * @param mixed $other The measurement to compare with.
     * @param float $relTol The relative tolerance for comparison.
     * @param float $absTol The absolute tolerance for comparison.
     * @return int -1 if this < other, 0 if equal, 1 if this > other.
     * @throws TypeError If the other Measurement has a different type.
     * @throws LogicException If no conversion path exists between the units.
     */
    public function approxCompare(
        mixed $other,
        float $relTol = Floats::DEFAULT_RELATIVE_TOLERANCE,
        float $absTol = Floats::DEFAULT_ABSOLUTE_TOLERANCE
    ): int {
        $otherValue = $this->preCompare($other);
        return Floats::approxCompare($this->value, $otherValue, $relTol, $absTol);
    }

    /**
     * Compare this Measurement with another and determine if they are equal, within user-defined tolerances.
     *
     * @param mixed $other The value to compare with (can be any type).
     * @return bool True if the values are equal, false otherwise.
     */
    public function approxEqual(
        mixed $other,
        float $relTol = Floats::DEFAULT_RELATIVE_TOLERANCE,
        float $absTol = Floats::DEFAULT_ABSOLUTE_TOLERANCE
    ): bool {
        return ($other instanceof static) && $this->approxCompare($other, $relTol, $absTol) === 0;
    }

    // endregion

    // region Arithmetic methods

    /**
     * Add another measurement to this one.
     *
     * Supports two call styles:
     * - add($otherMeasurement)
     * - add($value, $unit)
     *
     * Automatically converts units before adding.
     *
     * @param self|int|float $otherOrValue Another Measurement or a numeric value.
     * @param ?string $otherUnit The unit if providing a numeric value.
     * @return static A new Measurement containing the sum in this measurement's unit.
     * @throws TypeError If argument types are incorrect.
     * @throws ValueError If value is non-finite or unit is invalid.
     * @throws LogicException If no conversion path exists between units.
     *
     * @example
     *   $a = new Length(100, 'm');
     *   $b = new Length(2, 'km');
     *   $sum = $a->add($b);           // Length(2100, 'm')
     *   $sum2 = $a->add(50, 'cm');    // Length(100.5, 'm')
     */
    public function add(self|int|float $otherOrValue, ?string $otherUnit = null): static
    {
        // Validate and transform the arguments.
        $other = self::checkAddSubArgs($otherOrValue, $otherUnit);

        // Get the other Measurement in the same unit as this one.
        $otherValue = $this->unit === $other->unit
            ? $other->value
            : self::getUnitConverter()->convert($other->value, $other->unit, $this->unit);

        // Add the two values.
        return new static($this->value + $otherValue, $this->unit);
    }

    /**
     * Subtract another measurement from this one.
     *
     * Supports two call styles:
     * - sub($otherMeasurement)
     * - sub($value, $unit)
     *
     * Automatically converts units before subtracting.
     *
     * @param self|int|float $otherOrValue Another Measurement or a numeric value.
     * @param ?string $otherUnit The unit if providing a numeric value.
     * @return static A new Measurement containing the difference in this measurement's unit.
     * @throws TypeError If argument types are incorrect.
     * @throws ValueError If value is non-finite or unit is invalid.
     * @throws LogicException If no conversion path exists between units.
     *
     * @example
     *   $a = new Length(100, 'm');
     *   $b = new Length(2, 'km');
     *   $diff = $a->sub($b);          // Length(-1900, 'm')
     */
    public function sub(self|int|float $otherOrValue, ?string $otherUnit = null): static
    {
        // Validate and transform the arguments.
        $other = self::checkAddSubArgs($otherOrValue, $otherUnit);

        // Get the other Measurement in the same unit as this one.
        $otherValue = $this->unit === $other->unit
            ? $other->value
            : self::getUnitConverter()->convert($other->value, $other->unit, $this->unit);

        // Subtract the values.
        return new static($this->value - $otherValue, $this->unit);
    }

    /**
     * Negate a measurement.
     *
     * @return static A new Measurement containing the negative of this measurement's unit.
     */
    public function neg(): static
    {
        return new static(-$this->value, $this->unit);
    }

    /**
     * Multiply this measurement by a scalar factor.
     *
     * @param float $k The scale factor.
     * @return static A new Measurement with the value scaled.
     * @throws ValueError If the multiplier is non-finite (±∞ or NaN).
     *
     * @example
     *   $length = new Length(10, 'm');
     *   $doubled = $length->mul(2);  // Length(20, 'm')
     */
    public function mul(float $k): static
    {
        // Guard.
        if (!is_finite($k)) {
            throw new ValueError('Multiplier cannot be ±∞ or NaN.');
        }

        // Multiply the Measurement.
        return new static($this->value * $k, $this->unit);
    }

    /**
     * Divide this measurement by a scalar factor.
     *
     * @param float $k The divisor.
     * @return static A new Measurement with the value divided.
     * @throws DivisionByZeroError If the divisor is zero.
     * @throws ValueError If the divisor is non-finite (±∞ or NaN).
     *
     * @example
     *   $length = new Length(10, 'm');
     *   $half = $length->div(2);  // Length(5, 'm')
     */
    public function div(float $k): static
    {
        // Guards.
        if ($k === 0.0) {
            throw new DivisionByZeroError('Divisor cannot be 0.');
        }
        if (!is_finite($k)) {
            throw new ValueError('Divisor cannot be ±∞ or NaN.');
        }

        // Divide the Measurement.
        return new static(fdiv($this->value, $k), $this->unit);
    }

    /**
     * Get the absolute value of this measurement.
     *
     * @return static A new Measurement with non-negative value in the same unit.
     *
     * @example
     *   $temp = new Temperature(-10, 'C');
     *   $abs = $temp->abs();  // Temperature(10, 'C')
     */
    public function abs(): static
    {
        return new static(abs($this->value), $this->unit);
    }

    // endregion

    // region Static abstract methods (must be implemented in derived classes)

    /**
     * Define the base units for this measurement type.
     *
     * Returns an associative array where keys are unit symbols and values are integers
     * specifying which prefix sets are allowed for that unit.
     *
     * Base units should NOT include prefixes (e.g., use 'g' not 'kg', 'm' not 'km').
     *
     * Prefix set values:
     * - 0: No prefixes allowed
     * - PREFIXES_METRIC: All metric prefixes (quetta to quecto)
     * - PREFIXES_LARGE_METRIC: Large metric prefixes only (kilo and above)
     * - PREFIXES_SMALL_METRIC: Small metric prefixes only (milli and below)
     * - PREFIXES_BINARY: Binary prefixes (Ki, Mi, Gi, etc.)
     * - Combinations: Use bitwise OR (e.g., PREFIXES_METRIC | PREFIXES_BINARY)
     *
     * @return array<string, int> Map of unit symbol to prefix set flags.
     *
     * @example
     *   return [
     *       'm' => self::PREFIXES_METRIC,    // metre (all metric prefixes)
     *       'ft' => 0,                         // foot (no prefixes)
     *       'rad' => self::PREFIXES_SMALL_METRIC,  // radian (only small metric prefixes)
     *       'B' => self::PREFIXES_METRIC | self::PREFIXES_BINARY,  // byte (both sets)
     *   ];
     */
    abstract public static function getUnits(): array;

    // endregion

    // region Prefix methods (can be overridden)

    /**
     * Standard metric prefixes down to quecto (10^-30).
     *
     * Includes both standard symbols and alternatives (e.g., 'u' for micro).
     *
     * @return array<string, int|float>
     */
    public static function getSmallMetricPrefixes(): array
    {
        return [
            'q' => 1e-30,  // quecto
            'r' => 1e-27,  // ronto
            'y' => 1e-24,  // yocto
            'z' => 1e-21,  // zepto
            'a' => 1e-18,  // atto
            'f' => 1e-15,  // femto
            'p' => 1e-12,  // pico
            'n' => 1e-9,   // nano
            'μ' => 1e-6,   // micro
            'u' => 1e-6,   // micro (alias)
            'm' => 1e-3,   // milli
            'c' => 1e-2,   // centi
            'd' => 1e-1,   // deci
        ];
    }

    /**
     * Standard metric prefixes up to quetta (10^30).
     *
     * @return array<string, int|float>
     */
    public static function getLargeMetricPrefixes(): array
    {
        return [
            'da' => 1e1,    // deca
            'h'  => 1e2,    // hecto
            'k'  => 1e3,    // kilo
            'M'  => 1e6,    // mega
            'G'  => 1e9,    // giga
            'T'  => 1e12,   // tera
            'P'  => 1e15,   // peta
            'E'  => 1e18,   // exa
            'Z'  => 1e21,   // zetta
            'Y'  => 1e24,   // yotta
            'R'  => 1e27,   // ronna
            'Q'  => 1e30,   // quetta
        ];
    }

    /**
     * Define conversion factors between different units.
     *
     * Each conversion is an array with 3 or 4 elements:
     * - [0] string: Initial unit symbol
     * - [1] string: Final unit symbol
     * - [2] int|float: Multiplier (must be non-zero)
     * - [3] int|float: Optional offset (for affine conversions like temperature)
     *
     * Only direct conversions need to be specified; the system will automatically
     * find paths for indirect conversions (e.g., if you have m→ft and ft→in, it
     * can automatically convert m→in).
     *
     * @return array<int, array{0: string, 1: string, 2: int|float, 3?: int|float}> Array of conversion definitions.
     *
     * @example
     *   return [
     *       ['m', 'ft', 3.28084],          // 1 m = 3.28084 ft
     *       ['ft', 'in', 12],              // 1 ft = 12 in
     *       ['C', 'F', 1.8, 32],           // F = C * 1.8 + 32
     *   ];
     */
    abstract public static function getConversions(): array;

    /**
     * Binary prefixes for memory measurements.
     *
     * @return array<string, int|float>
     */
    public static function getBinaryPrefixes(): array
    {
        return [
            'Ki' => 2 ** 10, // kibi
            'Mi' => 2 ** 20, // mebi
            'Gi' => 2 ** 30, // gibi
            'Ti' => 2 ** 40, // tebi
            'Pi' => 2 ** 50, // pebi
            'Ei' => 2 ** 60, // exbi
            // These next two values will be represented as floats because they exceed PHP_INT_MAX, but they will still
            // be represented exactly because they're powers of 2.
            'Zi' => 2 ** 70, // zebi
            'Yi' => 2 ** 80, // yobi
        ];
    }

    /**
     * Return a set of prefixes, with multipliers, given an integer code comprising bitwise flags.
     *
     * This can be overridden in the derived class.
     *
     * @param int $prefixSetCode Code indicating the prefix sets to include.
     * @return array<string, int|float>
     */
    public static function getPrefixes(int $prefixSetCode = self::PREFIXES_ALL): array
    {
        $prefixes = [];

        if ($prefixSetCode & self::PREFIXES_SMALL_METRIC) {
            $prefixes = array_merge($prefixes, static::getSmallMetricPrefixes());
        }

        if ($prefixSetCode & self::PREFIXES_LARGE_METRIC) {
            $prefixes = array_merge($prefixes, static::getLargeMetricPrefixes());
        }

        if ($prefixSetCode & self::PREFIXES_BINARY) {
            $prefixes = array_merge($prefixes, static::getBinaryPrefixes());
        }

        return $prefixes;
    }

    // endregion

    // region Formatting methods (can be overridden)

    /**
     * Format a numeric value with specified precision and notation.
     *
     * Protected method called by format(). Can be overridden in derived classes
     * for custom formatting behavior.
     *
     * Precision meaning varies by specifier:
     * - 'f'/'F': Number of decimal places
     * - 'e'/'E': Number of mantissa digits
     * - 'g'/'G': Number of significant figures
     *
     * @param float $value The value to format.
     * @param string $specifier Format code: 'f', 'F', 'e', 'E', 'g', or 'G'.
     * @param ?int $precision Number of digits (meaning depends on specifier).
     * @param bool $trimZeros If true, remove trailing zeros and decimal point.
     * @return string The formatted value string.
     * @throws ValueError If arguments are invalid.
     *
     * @see https://www.php.net/manual/en/function.sprintf.php
     */
    protected static function formatValue(
        float $value,
        string $specifier = 'f',
        ?int $precision = null,
        bool $trimZeros = true
    ): string {
        // Validate the value (defensive check since this is a protected method).
        if (!is_finite($value)) {
            throw new ValueError('The value to format must be finite.');
        }

        // Validate the specifier.
        if (!in_array($specifier, ['e', 'E', 'f', 'F', 'g', 'G'], true)) {
            throw new ValueError("The specifier must be 'e', 'E', 'f', 'F', 'g', or 'G'.");
        }

        // Validate the precision.
        if ($precision !== null && ($precision < 0 || $precision > 17)) {
            throw new ValueError('The precision must be null or an integer between 0 and 17.');
        }

        // Canonicalize -0.0 to 0.0.
        $value = Floats::normalizeZero($value);

        // Format with the desired precision and specifier.
        // If the precision is null, omit it from the format string to use the sprintf default (usually 6).
        $formatString = $precision === null ? "%{$specifier}" : "%.{$precision}{$specifier}";
        $str = sprintf($formatString, $value);

        // Remove trailing zeros and decimal point from the number (i.e. the part before the 'E' or 'e', if present).
        if ($trimZeros) {
            $ePos = stripos($str, 'E');
            if ($ePos !== false) {
                $str = rtrim(substr($str, 0, $ePos), '0.') . substr($str, $ePos);
            } else {
                $str = rtrim($str, '0.');
            }
        }

        return $str;
    }

    /**
     * Format a unit symbol for display.
     *
     * Protected method called by format() and __toString(). Can be overridden
     * in derived classes for custom unit formatting.
     *
     * By default, converts 'u' prefix to 'μ' for better display (e.g., 'um' → 'μm').
     *
     * @param string $unit The unit symbol to format.
     * @return string The formatted unit symbol.
     */
    protected static function formatUnit(string $unit): string
    {
        // Convert 'u' to 'μ' if necessary. Looks better.
        $converter = static::getUnitConverter();
        [$prefix, $baseUnit, $exp] = $converter->decomposeUnit($unit);
        if ($prefix === 'u') {
            return $converter->composeUnit('μ', $baseUnit, $exp);
        }

        // Return the unit symbol as-is.
        return $unit;
    }

    // endregion

    // region Methods for working with parts

    /**
     * Get an array of units for use in parts-related methods.
     *
     * @return string[]
     */
    public static function getPartUnits(): array
    {
        return [];
    }

    /**
     * Check smallest unit argument is valid.
     *
     * @param string $smallestUnit
     * @return void
     * @throws ValueError
     */
    protected static function validateSmallestUnit(string $smallestUnit): void
    {
        $partUnits = static::getPartUnits();
        if (!in_array($smallestUnit, $partUnits, true)) {
            throw new ValueError('Invalid smallest unit specified. Must be one of: ' .
                                 implode(', ', Arrays::quoteValues($partUnits)));
        }
    }

    /**
     * Check precision argument is valid.
     *
     * @param ?int $precision The precision to validate.
     * @return void
     * @throws ValueError If precision is negative.
     */
    protected static function validatePrecision(?int $precision): void
    {
        if ($precision !== null && $precision < 0) {
            throw new ValueError('Invalid precision specified. Must be null or a non-negative integer.');
        }
    }

    /**
     * Validate the part units array.
     *
     * @return void
     * @throws LogicException
     */
    protected static function validatePartUnits(): void
    {
        // Ensure we have some part units.
        $partUnits = static::getPartUnits();
        if (empty($partUnits)) {
            throw new LogicException('Derived measurement type must define parts units');
        }

        // Ensure all part units are valid base units.
        $baseUnits = array_keys(static::getUnits());
        foreach ($partUnits as $partUnit) {
            if (!in_array($partUnit, $baseUnits, true)) {
                throw new LogicException('Part units must be valid base units.');
            }
        }
    }

    /**
     * Create a new Measurement object (of the derived type) as a sum of measurements of the same type in different
     * units.
     *
     * All parts must be non-negative.
     * If the final value should be negative, include a 'sign' part with a value of -1.
     *
     * @param array<string, int|float> $parts The parts and optional sign.
     * @return static A new Measurement equal to the sum of the parts, with the unit equal to the smallest unit.
     * @throws TypeError If any of the values are not numbers.
     * @throws ValueError If any of the values are non-finite or negative.
     * @throws LogicException If PARTS_UNITS is not defined in the derived class.
     */
    public static function fromPartsArray(array $parts): static
    {
        // Validate the part units.
        static::validatePartUnits();
        $partUnits = static::getPartUnits();

        // Validate the parts array.
        $validKeys = ['sign', ...$partUnits];
        foreach ($parts as $key => $value) {
            if (!in_array($key, $validKeys, true)) {
                throw new ValueError('Invalid part name: ' . $key);
            }
            if (!Numbers::isNumber($value)) {
                throw new TypeError('All values must be numbers.');
            }
            if ($key === 'sign') {
                if ($value !== -1 && $value !== 1) {
                    throw new ValueError('Sign must be -1 or 1.');
                }
            } elseif (!is_finite($value) || $value < 0.0) {
                throw new ValueError('All part values must be finite and non-negative.');
            }
        }

        // Initialize the Measurement to 0, with the unit set to the smallest unit.
        $smallestUnitIndex = array_key_last($partUnits);
        $smallestUnit = $partUnits[$smallestUnitIndex];
        $t = new (self::getClassName())(0, $smallestUnit);

        // Check each of the possible units.
        foreach ($partUnits as $unit) {
            // Ignore omitted units.
            if (!isset($parts[$unit])) {
                continue;
            }

            // Add the part. It will be converted to the smallest unit automatically.
            $t = $t->add($parts[$unit], $unit);
        }

        // Make negative if necessary.
        if (isset($parts['sign']) && $parts['sign'] === -1) {
            $t = $t->neg();
        }

        return $t;
    }

    /**
     * Convert Measurement to component parts.
     *
     * Returns an array with components from the largest to the smallest unit.
     * Only the last component may have a fractional part; others are integers.
     *
     * @param string $smallestUnit The smallest unit to include (default 'arcsec').
     * @param ?int $precision The number of decimal places for rounding the smallest unit, or null for no rounding.
     * @return array<string|int, int|float> Array of parts, plus the sign.
     * @throws ValueError If any arguments are invalid.
     * @throws LogicException If PARTS_UNITS is not defined in the derived class.
     */
    public function toParts(string $smallestUnit = 'arcsec', ?int $precision = null): array
    {
        // Validate arguments.
        static::validateSmallestUnit($smallestUnit);
        static::validatePrecision($precision);

        // Validate part units.
        static::validatePartUnits();
        $partUnits = static::getPartUnits();

        // Prep.
        $converter = static::getUnitConverter();
        $sign = Numbers::sign($this->value, false);
        $parts = ['sign' => $sign];
        $smallestUnitIndex = (int)array_search($smallestUnit, $partUnits, true);

        // Initialize the remainder to the initial value converted to the smallest unit.
        $rem = abs($this->to($smallestUnit)->value);

        // Get the integer parts.
        for ($i = 0; $i < $smallestUnitIndex; $i++) {
            // Get the number of current units in the smallest unit.
            $curUnit = $partUnits[$i];
            $factor = $converter->convert(1, $curUnit, $smallestUnit);
            $wholeNumCurUnit = floor($rem / $factor);
            $parts[$curUnit] = (int)$wholeNumCurUnit;
            $rem = $rem - $wholeNumCurUnit * $factor;
        }

        // Round the smallest unit.
        if ($precision === null) {
            // No rounding.
            $parts[$smallestUnit] = $rem;
        } elseif ($precision === 0) {
            // Return an integer.
            $parts[$smallestUnit] = (int)round($rem, $precision);
        } else {
            // Round off.
            $parts[$smallestUnit] = round($rem, $precision);
        }

        // Carry in reverse order.
        if ($precision !== null) {
            for ($i = $smallestUnitIndex; $i >= 1; $i--) {
                $curUnit = $partUnits[$i];
                $prevUnit = $partUnits[$i - 1];
                if ($parts[$curUnit] >= $converter->convert(1, $prevUnit, $curUnit)) {
                    $parts[$curUnit] = 0;
                    $parts[$prevUnit]++;
                }
            }
        }

        return $parts;
    }

    // endregion

    // region Static helper methods

    /**
     * Get the fully qualified class name of the derived measurement type.
     *
     * @return class-string The fully qualified class name.
     */
    private static function getClassName(): string
    {
        return static::class;
    }

    /**
     * Get or create the UnitConverter for this measurement type.
     *
     * UnitConverters are lazily initialized and cached per Measurement-derived class.
     * The constructor validates the base units and conversions.
     *
     * @return UnitConverter The unit converter instance.
     * @throws LogicException If the derived class configuration is invalid.
     */
    public static function getUnitConverter(): UnitConverter
    {
        // Get the name of the calling class.
        /** @var string $className */
        $className = self::getClassName();

        // Check the unit converter for the derived class has been validated and created.
        if (!isset(self::$unitConverters[$className])) {
            // Initialize the UnitConverter for this Measurement type.
            // The UnitConverter constructor will validate the class setup.
            try {
                self::$unitConverters[$className] = new UnitConverter(
                    static::getUnits(),
                    static::getPrefixes(),
                    static::getConversions()
                );
            } catch (LogicException $e) {
                throw new LogicException('The ' . self::getClassName() . ' class is not properly set up: ' .
                                         $e->getMessage());
            }
        }

        // Return the UnitConverter.
        return self::$unitConverters[$className];
    }

    /**
     * Validate and normalize arguments for add() and sub() methods.
     *
     * Supports two call styles:
     * - Single Measurement argument
     * - Separate value and unit arguments
     *
     * @param self|int|float $otherOrValue Another Measurement or a numeric value.
     * @param ?string $otherUnit The unit if providing a numeric value.
     * @return static The validated Measurement to add or subtract.
     * @throws TypeError If argument types are incorrect.
     * @throws ValueError If value is non-finite or unit is invalid.
     * @throws LogicException If the derived class is not properly configured.
     */
    protected static function checkAddSubArgs(self|int|float $otherOrValue, ?string $otherUnit = null): static
    {
        // One-parameter version.
        if ($otherOrValue instanceof static && $otherUnit === null) {
            return $otherOrValue;
        }

        // Two-parameter version.
        if (Numbers::isNumber($otherOrValue) && is_string($otherUnit)) {
            // This will throw if the value is non-finite or the unit is invalid.
            /** @var int|float $otherOrValue */
            return new static($otherOrValue, $otherUnit);
        }

        // Invalid argument types.
        $class = self::getClassName();
        throw new TypeError('Invalid argument types. Either the first argument must be an object of ' .
                            "type $class, and the second must be null or omitted; or, the first argument must be " .
                            'the value (int or float) of the measurement to add, and the second must be its unit ' .
                            '(string).');
    }

    // endregion
}
