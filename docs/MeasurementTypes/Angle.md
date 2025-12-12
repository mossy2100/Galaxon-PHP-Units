# Angle

Immutable class for working with angles in various units with high precision.

## Constants

- `RAD_EPSILON` - Epsilon for angle comparisons (1e-9)
- `TRIG_EPSILON` - Epsilon for trigonometric comparisons (1e-12)
- `UNIT_DEGREE` (0), `UNIT_ARCMINUTE` (1), `UNIT_ARCSECOND` (2) - Constants for specifying the smallest unit in DMS conversions

## Constructor

### __construct()

```php
public function __construct(float $size, string $unit = 'rad')
```

Create an angle with a specified size and unit.

**Parameters:**
- `$size` (float) - The size of the angle in the given units.
- `$unit` (string) - The unit: `'rad'`, `'deg'`, `'grad'`, or `'turn'` (default: `'rad'`)

**Throws:**
- `ValueError` - If the quantity is non-finite (±INF or NAN) or if the unit is invalid

**Examples:**
```php
// Radians (default unit)
$angle = new Angle(M_PI);
echo $angle->to('deg'); // 180.0

// Degrees
$angle = new Angle(180, 'deg');
echo $angle->to('rad');  // 3.14159...

// Gradians
$angle = new Angle(100, 'grad');
echo $angle->to('deg'); // 90.0

// Turns
$angle = new Angle(0.5, 'turn');
echo $angle->to('deg'); // 180.0
```

## Static Factory Methods

### fromDMS()

```php
public static function fromDMS(float $degrees, float $arcmin = 0.0, float $arcsec = 0.0): self
```

Create angle from degrees, plus optional arcminutes and arcseconds.

**Examples:**
```php
// Simple degrees
$angle = Angle::fromDMS(45.5);

// Degrees, arcminutes, arcseconds
$angle = Angle::fromDMS(12, 34, 56);  // 12° 34′ 56″

// Negative angle
$angle = Angle::fromDMS(-12, -34, -56);
```

### parse()

```php
public static function parse(string $value): self
```

Parse angle from string (supports CSS-style units and symbols for degrees, arcminutes, and arcseconds). Throws `ValueError` if invalid.

**Examples:**
```php
// CSS-style units
$angle = Angle::parse('45deg');
$angle = Angle::parse('1.5708rad');
$angle = Angle::parse('100grad');
$angle = Angle::parse('0.25turn');

// DMS notation (Unicode symbols)
$angle = Angle::parse('12° 34′ 56″');

// DMS notation (ASCII fallback)
$angle = Angle::parse("12°34'56\"");

// Whitespace and case insensitive
$angle = Angle::parse('  45 DEG  ');
```

## Conversion Methods

### to()

```php
public function to(string $unit): float
```

Convert the angle to the specified unit.

**Parameters:**
- `$unit` (string) - The target unit: `'rad'`, `'deg'`, `'grad'`, or `'turn'`

**Returns:**
- `float` - The angle in the specified unit

**Examples:**
```php
$angle = new Angle(180, 'deg');

echo $angle->to('rad');  // 3.14159...
echo $angle->to('deg');  // 180.0
echo $angle->to('grad'); // 200.0
echo $angle->to('turn'); // 0.5
```

### toDMS()

```php
public function toDMS(int $smallestUnit = Angle::UNIT_ARCSECOND): array
```

Get the angle in degrees, arcminutes, and arcseconds. The result will be an array with 1-3 values, depending on the requested smallest unit. Only the last item may have a fractional part; others will be whole numbers.

If the angle is positive, the resulting values will all be positive. If the angle is zero, the resulting values will all be zero. If the angle is negative, the resulting values will all be negative.

For the `$smallestUnit` parameter, you can use the UNIT_* class constants:
- `UNIT_DEGREE` (0) for degrees only
- `UNIT_ARCMINUTE` (1) for degrees and arcminutes
- `UNIT_ARCSECOND` (2) for degrees, arcminutes, and arcseconds (default)

(Note: If the smallest unit is degrees, you may prefer to use `to('deg')` instead, which returns a float instead of an array.)

**Parameters:**
- `$smallestUnit` (int) - 0 for degrees, 1 for arcminutes, 2 for arcseconds (default)

**Returns:**
- `float[]` - An array of 1-3 floats with the degrees, arcminutes, and arcseconds

**Throws:**
- `ValueError` - If $smallestUnit is not 0, 1, or 2

**Examples:**
```php
$angle = new Angle(M_PI / 4);

// As decimal degrees only
[$deg] = $angle->toDMS(Angle::UNIT_DEGREE);  // [45.0]

// As degrees and arcminutes
[$d, $m] = $angle->toDMS(Angle::UNIT_ARCMINUTE);  // [45.0, 0.0]

// As degrees, arcminutes, and arcseconds
[$d, $m, $s] = $angle->toDMS(Angle::UNIT_ARCSECOND);  // [45.0, 0.0, 0.0]

// Example with actual DMS values
$angle = Angle::fromDMS(12, 34, 56);
[$d, $m, $s] = $angle->toDMS();  // [12.0, 34.0, 56.0]
```

## Arithmetic Methods

### add()

```php
public function add(self $other): self
```

Add another angle to this angle.

**Example:**
```php
$a = new Angle(45, 'deg');
$b = new Angle(30, 'deg');
$sum = $a->add($b);
echo $sum->to('deg'); // 75.0
```

### sub()

```php
public function sub(self $other): self
```

Subtract another angle from this angle.

**Example:**
```php
$a = new Angle(90, 'deg');
$b = new Angle(45, 'deg');
$diff = $a->sub($b);
echo $diff->to('deg'); // 45.0
```

### mul()

```php
public function mul(float $k): self
```

Multiply angle by a scalar. Throws `ValueError` if the scalar is non-finite (±INF or NAN).

**Example:**
```php
$angle = new Angle(30, 'deg');
$doubled = $angle->mul(2);
echo $doubled->to('deg'); // 60.0
```

### div()

```php
public function div(float $k): self
```

Divide angle by a scalar. Throws `DivisionByZeroError` if divisor is zero, `ValueError` if divisor is non-finite.

**Example:**
```php
$angle = new Angle(90, 'deg');
$half = $angle->div(2);
echo $half->to('deg'); // 45.0
```

### abs()

```php
public function abs(): self
```

Get absolute value of angle.

**Example:**
```php
$angle = new Angle(-45, 'deg');
$positive = $angle->abs();
echo $positive->to('deg'); // 45.0
```

### wrap()

```php
public function wrap(bool $signed = true): self
```

Normalize this angle. Returns a new Angle with the wrapped value (the original is unchanged).

**Parameters:**
- `$signed` (bool) - Whether to use signed range (default: `true`)

**Examples:**
```php
// Signed wrapping - DEFAULT
$angle = new Angle(200, 'deg');
$wrapped = $angle->wrap();
echo $wrapped->to('deg'); // -160.0

// Unsigned wrapping
$angle = new Angle(450, 'deg');
$wrapped = $angle->wrap(false);
echo $wrapped->to('deg'); // 90.0

// Chaining
$result = new Angle(540, 'deg')
    ->wrap()
    ->mul(2);
echo $result->to('deg'); // 360.0
```

## Comparison Methods

Angle implements the `Equatable` interface and uses the `Comparable` trait, providing a full set of comparison operations.

### compare()
```php
public function compare(mixed $other): int
```

Compare angles by their raw numeric values with relative epsilon tolerance. Returns -1 if this angle is less, 0 if equal (within epsilon), 1 if greater.

Angles are compared using their raw radian values without normalization, so 10° < 370° even though they represent the same angular position. Use `wrap()` before comparing if you need to treat equivalent positions as equal.

Two angles are considered equal if their difference in radians is less than `RAD_EPSILON` (1e-9).

**Parameters:**
- `$other` (mixed) - The value to compare with (must be an Angle)

**Returns:**
- `int` - -1 if this < other, 0 if equal (within epsilon), 1 if this > other

**Throws:**
- `TypeError` - If $other is not an Angle

**Example:**
```php
$a = new Angle(10, 'deg');
$b = new Angle(370, 'deg');
echo $a->compare($b); // -1 (10 < 370)

$c = new Angle(10, 'deg');
echo $a->compare($c); // 0 (equal)

// Wrapped comparison
$aWrapped = new Angle(10, 'deg')->wrap();
$bWrapped = new Angle(370, 'deg')->wrap();
echo $aWrapped->compare($bWrapped); // 0 (both normalized to 10°)
```

### equal()
```php
public function equal(mixed $other): bool
```

Check if two angles are equal. Provided by the `Comparable` trait - delegates to `compare()`.

Angles are not normalized before comparison, so use `wrap()` first if you need to compare angular positions rather than raw values.

**Parameters:**
- `$other` (mixed) - The value to compare with

**Returns:**
- `bool` - True if angles are exactly equal; false otherwise

**Note:** Returns `false` gracefully for non-Angle types (doesn't throw).

**Example:**
```php
$a = new Angle(45, 'deg');
$b = new Angle(45, 'deg');
$c = new Angle(405, 'deg'); // 45° + 360°

var_dump($a->equal($b)); // true
var_dump($a->equal($c)); // false (45 ≠ 405)

// After wrapping
$cWrapped = new Angle(405, 'deg')->wrap();
var_dump($a->equal($cWrapped)); // true (both are 45°)

// Gracefully handles wrong types
var_dump($a->equal(45)); // false (not an Angle)
var_dump($a->equal("45deg")); // false (not an Angle)
```

### lessThan()
```php
public function lessThan(mixed $other): bool
```

Check if this angle is less than another. Provided by the `Comparable` trait.

**Example:**
```php
$a = new Angle(30, 'deg');
$b = new Angle(60, 'deg');

var_dump($a->lessThan($b)); // true
var_dump($b->lessThan($a)); // false
```

### lessThanOrEqual()
```php
public function lessThanOrEqual(mixed $other): bool
```

Check if this angle is less than or equal to another. Provided by the `Comparable` trait.

**Example:**
```php
$a = new Angle(45, 'deg');
$b = new Angle(45, 'deg');
$c = new Angle(90, 'deg');

var_dump($a->lessThanOrEqual($b)); // true (equal)
var_dump($a->lessThanOrEqual($c)); // true (less than)
```

### greaterThan()
```php
public function greaterThan(mixed $other): bool
```

Check if this angle is greater than another. Provided by the `Comparable` trait.

**Example:**
```php
$a = new Angle(90, 'deg');
$b = new Angle(45, 'deg');

var_dump($a->greaterThan($b)); // true
var_dump($b->greaterThan($a)); // false
```

### greaterThanOrEqual()
```php
public function greaterThanOrEqual(mixed $other): bool
```

Check if this angle is greater than or equal to another. Provided by the `Comparable` trait.

**Example:**
```php
$a = new Angle(60, 'deg');
$b = new Angle(60, 'deg');
$c = new Angle(30, 'deg');

var_dump($a->greaterThanOrEqual($b)); // true (equal)
var_dump($a->greaterThanOrEqual($c)); // true (greater than)
```

## Trigonometric Functions

### sin(), cos(), tan()

```php
public function sin(): float
public function cos(): float
public function tan(): float
```

Standard trigonometric functions.

**Example:**
```php
$angle = new Angle(30, 'deg');
echo $angle->sin(); // 0.5
echo $angle->cos(); // 0.866...
echo $angle->tan(); // 0.577...
```

### sec(), csc(), cot()

```php
public function sec(): float
public function csc(): float
public function cot(): float
```

Reciprocal trigonometric functions: secant (1/cos), cosecant (1/sin), cotangent (cos/sin).

Returns `±INF` at singularities (e.g., `sec(90°)`, `csc(0°)`, `cot(0°)`).

**Example:**
```php
$angle = new Angle(60, 'deg');
echo $angle->sec(); // 2.0
echo $angle->csc(); // 1.154...
echo $angle->cot(); // 0.577...
```

## Hyperbolic Functions

### sinh(), cosh(), tanh()

```php
public function sinh(): float
public function cosh(): float
public function tanh(): float
```

Hyperbolic functions.

**Example:**
```php
$angle = new Angle(1.0);
echo $angle->sinh(); // 1.175...
echo $angle->cosh(); // 1.543...
echo $angle->tanh(); // 0.761...
```

### sech(), csch(), coth()

```php
public function sech(): float
public function csch(): float
public function coth(): float
```

Reciprocal hyperbolic functions: hyperbolic secant (1/cosh), hyperbolic cosecant (1/sinh), hyperbolic cotangent (cosh/sinh).

Returns `±INF` at singularities (e.g., `csch(0)`, `coth(0)`).

**Example:**
```php
$angle = new Angle(1.0);
echo $angle->sech(); // 0.648...
echo $angle->csch(); // 0.850...
echo $angle->coth(); // 1.313...
```

## String Methods

### format()

```php
public function format(string $unit = 'rad', ?int $decimals = null): string
```

Format angle in CSS style, with no space between number and unit.
Supported units are `'rad'`, `'deg'`, `'grad'`, and `'turn'`.

The `$decimals` parameter controls decimal places. If `null`, maximum precision is used with trailing zeros removed.

**Examples:**
```php
$angle = new Angle(12.5, 'deg');

// Different units
echo $angle->format('rad', 4);  // 0.2182rad
echo $angle->format('deg', 2);  // 12.50deg
echo $angle->format('grad', 3); // 13.889grad
echo $angle->format('turn', 5); // 0.03472turn

// Maximum precision (default)
echo $angle->format('rad'); // 0.21816615649929rad

// Complex angle
$angle = Angle::fromDMS(45, 30, 15);
echo $angle->format('deg', 4); // 45.5042deg
```

### formatDMS()

```php
public function formatDMS(int $smallestUnit = UNIT_ARCSECOND, ?int $decimals = null): string
```

Options for $smallestUnit:
- `UNIT_DEGREE` - Degrees only with ° symbol
- `UNIT_ARCMINUTE` - Degrees and arcminutes with ° ′ symbols
- `UNIT_ARCSECOND` - Degrees, arcminutes, and arcseconds with ° ′ ″ symbols


**Examples:**
```php
$angle = new Angle(12.5, 'deg');

// DMS formats
echo $angle->formatDMS(Angle::UNIT_DEGREE, 1);     // 12.5°
echo $angle->formatDMS(Angle::UNIT_ARCMINUTE, 0);  // 12° 30′
echo $angle->formatDMS(Angle::UNIT_ARCSECOND, 2);  // 12° 30′ 0.00″

// Complex angle
$angle = Angle::fromDMS(45, 30, 15);
echo $angle->formatDMS(decimals: 1); // 45° 30′ 15.0″

// Negative angles
$angle = Angle::fromDMS(-30, -15, -45);
echo $angle->formatDMS(); // -30° 15′ 45″
```

**Carry behavior:**

When rounding with `$decimals`, the formatter handles carry correctly:

```php
$angle = Angle::fromDMS(29, 59, 59.9999);
echo $angle->formatDMS(decimals: 3); // 30° 0′ 0.000″ (carried to next degree)

$angle = Angle::fromDMS(29, 59.9999);
echo $angle->formatDMS(Angle::UNIT_ARCMINUTE, 3); // 30° 0.000′ (carried to next degree)
```

### __toString()

```php
public function __toString(): string
```

Convert to string in CSS notation using radians as the unit, with maximum precision.

**Example:**
```php
$angle = new Angle(45, 'deg');
echo $angle; // 0.78539816339745rad
echo (string)$angle; // 0.78539816339745rad
```

## Static Wrapping Methods

These static utility methods normalize raw float values to a canonical range. They do not operate on Angle objects - use the `wrap()` instance method for that.

Wrapping follows the mathematical convention where:
- **Unsigned range [0, 2π)**: Includes lower bound (0), excludes upper bound (2π)
- **Signed range (-π, π]**: Excludes lower bound (-π), includes upper bound (π)

This convention matches the [standard principal value for complex number arguments](https://en.wikipedia.org/wiki/Principal_value#Complex_argument) and ensures uniqueness.

### wrapRadians()

```php
public static function wrapRadians(float $radians, bool $signed = true): float
```

Normalize radians into the signed range (-π, π] by default, or unsigned range [0, τ) when `$signed = false`.

**Parameters:**
- `$radians` (float) - The angle in radians to normalize
- `$signed` (bool) - Whether to use signed range (default: `true`)

**Examples:**
```php
// Signed range (-π, π] - DEFAULT
$wrapped = Angle::wrapRadians(4.0); // -2.283... (4 - 2π)
$wrapped = Angle::wrapRadians(-M_PI); // π (lower bound excluded, wraps to upper)
$wrapped = Angle::wrapRadians(M_PI); // π (upper bound included)

// Unsigned range [0, 2π)
$wrapped = Angle::wrapRadians(7.0, false); // 0.716... (7 - 2π)
$wrapped = Angle::wrapRadians(-M_PI, false); // π (negative wraps to positive)
```

### wrapDegrees()

```php
public static function wrapDegrees(float $degrees, bool $signed = true): float
```

Normalize degrees into the signed range (-180, 180] by default, or unsigned range [0, 360) when `$signed = false`.

**Parameters:**
- `$degrees` (float) - The angle in degrees to normalize
- `$signed` (bool) - Whether to use signed range (default: `true`)

**Examples:**
```php
// Signed range (-180, 180] - DEFAULT
$wrapped = Angle::wrapDegrees(200); // -160.0
$wrapped = Angle::wrapDegrees(-180); // 180.0 (lower bound excluded, wraps to upper)
$wrapped = Angle::wrapDegrees(180); // 180.0 (upper bound included)

// Unsigned range [0, 360)
$wrapped = Angle::wrapDegrees(450, false); // 90.0
$wrapped = Angle::wrapDegrees(-90, false); // 270.0 (negative wraps to positive)
```

### wrapGradians()

```php
public static function wrapGradians(float $gradians, bool $signed = true): float
```

Normalize gradians into the signed range (-200, 200] by default, or unsigned range [0, 400) when `$signed = false`.

**Parameters:**
- `$gradians` (float) - The angle in gradians to normalize
- `$signed` (bool) - Whether to use signed range (default: `true`)

**Examples:**
```php
// Signed range (-200, 200] - DEFAULT
$wrapped = Angle::wrapGradians(250); // -150.0
$wrapped = Angle::wrapGradians(-200); // 200.0 (lower bound excluded, wraps to upper)
$wrapped = Angle::wrapGradians(200); // 200.0 (upper bound included)

// Unsigned range [0, 400)
$wrapped = Angle::wrapGradians(500, false); // 100.0
$wrapped = Angle::wrapGradians(-100, false); // 300.0 (negative wraps to positive)
```

### wrapTurns()

```php
public static function wrapTurns(float $turns, bool $signed = true): float
```

Normalize turns into the signed range (-0.5, 0.5] by default, or unsigned range [0, 1) when `$signed = false`.

**Parameters:**
- `$turns` (float) - The angle in turns to normalize
- `$signed` (bool) - Whether to use signed range (default: `true`)

**Examples:**
```php
// Signed range (-0.5, 0.5] - DEFAULT
$wrapped = Angle::wrapTurns(0.75); // -0.25
$wrapped = Angle::wrapTurns(-0.5); // 0.5 (lower bound excluded, wraps to upper)
$wrapped = Angle::wrapTurns(0.5); // 0.5 (upper bound included)

// Unsigned range [0, 1)
$wrapped = Angle::wrapTurns(1.25, false); // 0.25
$wrapped = Angle::wrapTurns(-0.25, false); // 0.75 (negative wraps to positive)
```

## Usage Examples

### Basic angle creation and conversion

```php
// Create angle in various units
$rad = new Angle(M_PI / 2);
$deg = new Angle(90, 'deg');
$grad = new Angle(100, 'grad');
$turn = new Angle(0.25, 'turn');

// All represent the same angle (90°)
var_dump($rad->equal($deg)); // true
var_dump($deg->equal($grad)); // true
var_dump($grad->equal($turn)); // true
```

### Working with DMS (degrees, minutes, seconds)

```php
// Create from DMS
$latitude = Angle::fromDMS(40, 46, 11.5);  // New York City

// Convert to different representations
echo $latitude->to('deg');  // 40.769861111111

// Get as DMS array
[$d, $m, $s] = $latitude->toDMS();
echo "{$d}° {$m}′ {$s}″";  // 40° 46′ 11.5″

// Format as string
echo $latitude->formatDMS(decimals: 1);  // "40° 46′ 11.5″"
```

### Angle arithmetic

```php
// Calculate the sum of two angles
$bearing1 = new Angle(45, 'deg');
$adjustment = new Angle(30, 'deg');
$newBearing = $bearing1->add($adjustment);
echo $newBearing->format('deg', 0);    // "75deg"

// Scale an angle
$angle = new Angle(30, 'deg');
$tripled = $angle->mul(3);
echo $tripled->format('deg', 0);       // "90deg"

// Calculate average angle
$a1 = new Angle(30, 'deg');
$a2 = new Angle(60, 'deg');
$avg = $a1->add($a2)->div(2);
echo $avg->format('deg', 0);           // "45deg"
```

### Wrapping angles

```php
// Normalize angle to [0, 360) range
$angle = new Angle(450, 'deg');
$wrapped = $angle->wrap(false);
echo $wrapped->format('deg', 0);       // "90deg"

// Normalize to (-180, 180] range
$angle = new Angle(270, 'deg');
$wrapped = $angle->wrap();
echo $wrapped->format('deg', 0);       // "-90deg"
```

### Parsing angle strings

```php
// Parse various formats
$angles = [
    Angle::parse('45deg'),
    Angle::parse('1.5708rad'),
    Angle::parse('100grad'),
    Angle::parse('0.25turn'),
    Angle::parse('45° 30′ 0″'),
];
```

### Trigonometry

```php
// Calculate height of a building
$distance = 100; // meters
$angle = new Angle(30, 'deg');
$height = $distance * $angle->tan();
echo round($height, 2); // 57.74 meters

// Navigate using bearings
$bearing = new Angle(45, 'deg');
$distance = 100;
$eastward = $distance * $bearing->sin();
$northward = $distance * $bearing->cos();
```
