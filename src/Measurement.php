<?php

declare(strict_types=1);

namespace Galaxon\Units;

use DivisionByZeroError;
use Galaxon\Core\Arrays;
use Galaxon\Core\Floats;
use Galaxon\Core\Numbers;
use Galaxon\Core\Traits\ApproxComparable;
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
 * - getUnits(): Define the base and derived units, and specify the prefixes they accept.
 * (A derived unit is a base unit with an exponent, e.g. 'm3'.)
 * - Optionally override getConversions(): Define conversion factors between units
 *
 * Prefix system:
 * - Units can specify allowed prefixes using bitwise flags (PREFIX_CODE_METRIC, PREFIX_CODE_BINARY, etc.)
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
    use ApproxComparable;

    // region Constants

    /**
     * Constants for prefix set codes.
     */
    public const int PREFIX_CODE_SMALL_METRIC = 1;
    public const int PREFIX_CODE_LARGE_METRIC = 2;
    public const int PREFIX_CODE_BINARY = 4;
    public const int PREFIX_CODE_METRIC = self::PREFIX_CODE_SMALL_METRIC | self::PREFIX_CODE_LARGE_METRIC;
    public const int PREFIX_CODE_LARGE = self::PREFIX_CODE_LARGE_METRIC | self::PREFIX_CODE_BINARY;
    public const int PREFIX_CODE_ALL = self::PREFIX_CODE_METRIC | self::PREFIX_CODE_BINARY;

    // endregion

    // region Properties
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
     * Cache of UnitConverter instances, one per Measurement-derived class.
     *
     * Indexed by fully-qualified class name.
     *
     * @var array<class-string, UnitConverter>
     */
    private static array $unitConverters = [];

    // endregion
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
     * @param float $value The numeric value in the given unit.
     * @param string $unit The unit (e.g., 'kg', 'mm', 'hr').
     * @throws ValueError If the value is non-finite (±INF or NAN) or if the unit is invalid.
     * @throws LogicException If the derived class is not properly configured.
     */
    final public function __construct(float $value, string $unit)
    {
        // Check the value is finite.
        if (!is_finite($value)) {
            throw new ValueError('Measurement value cannot be ±INF or NAN.');
        }

        // Ensure the UnitConverter has been validated and created.
        $unitConverter = static::getUnitConverter();

        // Check the unit is valid.
        $unitConverter->checkIsValidUnitSymbol($unit);

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
    public static function parse(string $value): static
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
        $rxNum = '[-+]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?';
        $units = static::getUnitConverter()->getUnitSymbols();
        $rxUnits = implode('|', array_map('preg_quote', $units));
        if (preg_match("/^($rxNum)\s*($rxUnits)$/", $value, $m)) {
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

    // region Abstract methods

    /**
     * Define the base and derived units for this measurement type.
     *
     * Returns an associative array where keys are unit symbols and values are integers specifying the prefix sets
     * allowed for that unit.
     *
     * These units should NOT include multiplier (e.g. metric or binary) prefixes (e.g., use 'g' not 'kg', 'm' not
     * 'km').
     *
     * Prefix set values:
     * - 0: No prefixes allowed
     * - PREFIX_CODE_METRIC: All metric prefixes (quecto to quetta)
     * - PREFIX_CODE_SMALL_METRIC: Small metric prefixes only (quecto to deci)
     * - PREFIX_CODE_LARGE_METRIC: Large metric prefixes only (deca to quetta)
     * - PREFIX_CODE_BINARY: Binary prefixes (Ki, Mi, Gi, etc.)
     * - PREFIX_CODE_LARGE: All binary and large metric prefixes (k, Ki, etc.)
     *
     * @return array<string, int> Map of unit symbol to prefix set flags.
     *
     * @example
     *   return [
     *       'm'   => self::PREFIX_CODE_METRIC,        // metre (all metric prefixes)
     *       'ft'  => 0,                            // foot (no prefixes)
     *       'rad' => self::PREFIX_CODE_SMALL_METRIC,  // radian (only small metric prefixes)
     *       'B'   => self::PREFIX_CODE_LARGE,         // byte (binary and large metric)
     *   ];
     */
    abstract public static function getUnits(): array;

    /**
     * Define conversion factors between different units.
     *
     * Each conversion is an array with 3 or 4 elements:
     * - [0] string: Initial unit symbol
     * - [1] string: Final unit symbol
     * - [2] float: Multiplier (must be non-zero)
     * - [3] float: Optional offset (for affine conversions like temperature)
     *
     * Only direct conversions need to be specified; the system will automatically
     * find paths for indirect conversions (e.g., if you have m→ft and ft→in, it
     * can automatically convert m→in).
     *
     * @return array<array{0: string, 1: string, 2: float, 3?: float}> Array of conversion definitions.
     *
     * @example
     *   return [
     *       ['m', 'ft', 3.28084],          // 1 m = 3.28084 ft
     *       ['ft', 'in', 12],              // 1 ft = 12 in
     *       ['C', 'F', 1.8, 32],           // F = C * 1.8 + 32
     *   ];
     */
    abstract public static function getConversions(): array;

    // endregion

    // region Extraction methods

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
        $className = static::class;

        // Check the unit converter for the derived class has been validated and created.
        if (!isset(self::$unitConverters[$className])) {
            // Initialize the UnitConverter for this Measurement type.
            // The UnitConverter constructor will validate the class setup.
            try {
                self::$unitConverters[$className] = new UnitConverter(
                    static::getUnits(),
                    static::getConversions()
                );
            } catch (LogicException $e) {
                throw new LogicException("The $className class is not properly set up: " . $e->getMessage());
            }
        }

        // Return the UnitConverter.
        return self::$unitConverters[$className];
    }

    // endregion

    // region Transformation methods

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

    // endregion

    // region Comparison methods

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
     * Compare this Measurement with another and determine if they are equal, within user-defined tolerances.
     *
     * @param mixed $other The value to compare with (can be any type).
     * @return bool True if the values are equal, false otherwise.
     * @throws LogicException If no conversion path exists between the units.
     */
    #[Override]
    public function approxEqual(
        mixed $other,
        float $relTol = Floats::DEFAULT_RELATIVE_TOLERANCE,
        float $absTol = Floats::DEFAULT_ABSOLUTE_TOLERANCE
    ): bool {
        // Get the other Measurement's value in the same unit.
        try {
            // This will throw a TypeError if the other Measurement has a different type.
            $otherValue = $this->preCompare($other);
        } catch (TypeError) {
            // If the other Measurement has a different type, it's not equal to this one.
            return false;
        }

        // Now we have the other Measurement in the same unit, compare the values.
        return Floats::approxEqual($this->value, $otherValue, $relTol, $absTol);
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
     * @param self|float $otherOrValue Another Measurement or a numeric value.
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
    public function add(self|float $otherOrValue, ?string $otherUnit = null): static
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
     * @param self|float $otherOrValue Another Measurement or a numeric value.
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
    public function sub(self|float $otherOrValue, ?string $otherUnit = null): static
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
     * @throws ValueError If the multiplier is non-finite (±INF or NAN).
     *
     * @example
     *   $length = new Length(10, 'm');
     *   $doubled = $length->mul(2);  // Length(20, 'm')
     */
    public function mul(float $k): static
    {
        // Guard.
        if (!is_finite($k)) {
            throw new ValueError('Multiplier cannot be ±INF or NAN.');
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
     * @throws ValueError If the divisor is non-finite (±INF or NAN).
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
            throw new ValueError('Divisor cannot be ±INF or NAN.');
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

    // region Formatting methods
    // region Instance formatting methods

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
        bool $includeSpace = false
    ): string {
        // Return the formatted string. Arguments will be validated in formatValue().
        return static::formatValue($this->value, $specifier, $precision, $trimZeros)
               . ($includeSpace ? ' ' : '') . static::formatUnit($this->unit);
    }

    // endregion

    // region Static formatting methods

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

        // If $trimZeros is true and there's a decimal point in the string, remove trailing zeros and decimal point from
        // the number. If there's an 'E' or 'e' in the string, this only applies to the mantissa.
        if ($trimZeros && str_contains($str, '.')) {
            $ePos = stripos($str, 'E');
            $mantissa = $ePos === false ? $str : substr($str, 0, $ePos);
            $exp = $ePos === false ? '' : substr($str, $ePos);
            $str = rtrim($mantissa, '0.') . $exp;
        }

        return $str;
    }

    /**
     * Format a unit symbol for display.
     *
     * Called by format() and __toString().
     * Can be overridden in derived classes for custom unit formatting.
     *
     * @param string $unit The unit symbol to format.
     * @return string The formatted unit symbol.
     */
    public static function formatUnit(string $unit): string
    {
        return (string)static::getUnitConverter()->getUnit($unit);
    }

    // endregion
    // endregion

    // region Conversion methods

    /**
     * Convert the measurement to a string using basic formatting.
     *
     * Uses PHP's default float-to-string conversion with normalized zero.
     * For custom formatting, use format() instead.
     *
     * @return string The measurement as a string (e.g., "1.5707963267949rad").
     */
    #[Override]
    public function __toString(): string
    {
        return Floats::normalizeZero($this->value) . static::formatUnit($this->unit);
    }

    // endregion

    // region Part-related methods

    /**
     * Get an array of units for use in part-related methods.
     *
     * @return array<int|string, string>
     */
    public static function getPartUnits(): array
    {
        return [];
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
     * @throws LogicException If getPartUnits() has not been overridden properly.
     */
    public static function fromPartsArray(array $parts): static
    {
        // Validate and get part units.
        $symbols = static::validateAndTransformPartUnits();
        $partUnits = array_keys($symbols);

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
        $smallestUnit = $partUnits[$smallestUnitIndex]; // @phpstan-ignore offsetAccess.notFound
        $t = new (static::class)(0, $smallestUnit);

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
     * @param string $smallestUnit The smallest unit to include.
     * @param ?int $precision The number of decimal places for rounding the smallest unit, or null for no rounding.
     * @return array<string, int|float> Array of parts, plus the sign, which is always 1 or -1.
     * @throws ValueError If any arguments are invalid.
     * @throws LogicException If getPartUnits() has not been overridden properly.
     */
    public function toPartsArray(string $smallestUnit, ?int $precision = null): array
    {
        // Validate arguments.
        static::validateSmallestUnit($smallestUnit);
        static::validatePrecision($precision);

        // Validate and get part units.
        $symbols = static::validateAndTransformPartUnits();
        $partUnits = array_keys($symbols);

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

    /**
     * Format measurement as component parts.
     *
     * Examples:
     *   - 4y 5mo 6d 12h 34min 56.789s
     *   - 12° 34′ 56.789″
     *
     * Only the smallest unit may have a decimal point. Larger units will be integers.
     *
     * @param string $smallestUnit The smallest unit to include.
     * @param ?int $precision The number of decimal places for rounding the smallest unit, or null for no rounding.
     * @param bool $showZeros If true, show all components (largest to smallest) including zeros; if false, skip
     * zero-value components.
     * @return string Formatted string.
     * @throws ValueError If any arguments are invalid.
     */
    public function formatParts(string $smallestUnit, ?int $precision = null, bool $showZeros = false): string
    {
        // Validate arguments.
        self::validateSmallestUnit($smallestUnit);
        self::validatePrecision($precision);

        // Validate and get part units.
        $symbols = static::validateAndTransformPartUnits();
        $partUnits = array_keys($symbols);

        // Prep.
        $parts = $this->toPartsArray($smallestUnit, $precision);
        $smallestUnitIndex = (int)array_search($smallestUnit, $partUnits, true);
        $result = [];
        $hasNonZero = false;

        // Generate string as parts.
        for ($i = 0; $i <= $smallestUnitIndex; $i++) {
            $unit = $partUnits[$i];
            $value = $parts[$unit] ?? 0;
            $isZero = Numbers::equal($value, 0);

            // Track if we've seen any non-zero values.
            if (!$isZero) {
                $hasNonZero = true;
            }

            // Skip zero components based on $showZeros flag.
            // When $showZeros is true: show zeros only after the first non-zero (standard DMS notation).
            // When $showZeros is false: skip all zeros (compact time notation).
            if ($isZero && !($showZeros && $hasNonZero)) {
                continue;
            }

            // Format the value with precision for the smallest unit.
            $formattedValue = $i === $smallestUnitIndex && $precision !== null
                ? number_format($value, $precision, '.', '')
                : (string)$value;

            $result[] = $formattedValue . $symbols[$unit];
        }

        // If the value is zero, just show '0' with the smallest unit.
        if (empty($result)) {
            $formattedValue = $precision === null ? '0' : number_format(0, $precision, '.', '');
            $result[] = $formattedValue . $symbols[$smallestUnit];
        }

        // Return string of units, separated by spaces. Prepend minus sign if negative.
        return ($parts['sign'] === -1 ? '-' : '') . implode(' ', $result);
    }

    // endregion

    // region Helper methods

    /**
     * Check the $this and $other objects have the same type, and get the value of the $other Measurement in the same
     * unit as the $this one. Return the value.
     *
     * @param mixed $other The other measurement to compare with.
     * @return float The value of the other measurement in the same unit as this one.
     * @throws LogicException If no conversion path exists between the units.
     * @throws TypeError If the other Measurement has a different type.
     * @throws ValueError If the target unit is invalid.
     */
    protected function preCompare(mixed $other): float
    {
        // Check the two measurements have the same types.
        if (!Types::same($this, $other)) {
            throw new TypeError('The two measurements being compared must be of the same type.');
        }

        // Get the other Measurement in the same unit as this one.
        /** @var Measurement $other */
        return $this->unit === $other->unit ? $other->value : $other->to($this->unit)->value;
    }

    /**
     * Validate and normalize arguments for add() and sub() methods.
     *
     * Supports two call styles:
     * - Single Measurement argument
     * - Separate value and unit arguments
     *
     * @param self|float $otherOrValue Another Measurement or a numeric value.
     * @param ?string $otherUnit The unit if providing a numeric value.
     * @return static The validated Measurement to add or subtract.
     * @throws TypeError If argument types are incorrect.
     * @throws ValueError If value is non-finite or unit is invalid.
     * @throws LogicException If the derived class is not properly configured.
     */
    protected static function checkAddSubArgs(self|float $otherOrValue, ?string $otherUnit = null): static
    {
        // One-parameter version.
        if ($otherOrValue instanceof static && $otherUnit === null) {
            return $otherOrValue;
        }

        // Two-parameter version.
        if (Numbers::isNumber($otherOrValue) && is_string($otherUnit)) {
            // This will throw if the value is non-finite or the unit is invalid.
            /** @var float $otherOrValue */
            return new static($otherOrValue, $otherUnit);
        }

        // Invalid argument types.
        $class = static::class;
        throw new TypeError('Invalid argument types. Either the first argument must be an object of ' .
                            "type $class, and the second must be null or omitted; or, the first argument must be " .
                            'the value (int or float) of the measurement to add, and the second must be its unit ' .
                            '(string).');
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
        // Validate and get part units.
        $symbols = static::validateAndTransformPartUnits();
        $partUnits = array_keys($symbols);

        // Check the smallest unit is valid.
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
     * Validate and transform the part units array.
     *
     * @return array<string, string>
     * @throws LogicException If getPartUnits() returns an empty array, or if any of the units or symbols are invalid.
     */
    protected static function validateAndTransformPartUnits(): array
    {
        // Get the part units array. This should be overridden in the derived class and return a non-empty array.
        $partUnits = static::getPartUnits();

        // Ensure we have some part units.
        if (empty($partUnits)) {
            throw new LogicException(
                'The derived Measurement class must define the part units by overriding getPartUnits(), so ' .
                'it returns an array of valid units (with optional alternative symbols).'
            );
        }

        // Create a new array to contain the map of units to symbols.
        $symbols = [];

        // Ensure all part units are valid units.
        $validUnits = array_keys(static::getUnits());
        foreach ($partUnits as $partUnit => $symbol) {
            // If the key is an integer, the unit and the symbol are the same.
            if (is_int($partUnit)) {
                $partUnit = $symbol;
            }

            // Ensure the unit is valid.
            if (!in_array($partUnit, $validUnits, true)) {
                throw new LogicException("Invalid part unit: '$partUnit'.");
            }

            // Ensure the symbol is a non-empty string.
            if (!is_string($symbol) || $symbol === '') {
                throw new LogicException('Unit symbols must be non-empty strings.');
            }

            // Add it to the result.
            $symbols[$partUnit] = $symbol;
        }

        return $symbols;
    }

    // endregion
}
