# Galaxon PHP Units

Physical measurement types with automatic unit conversion and prefix support.

**[License](LICENSE)** | **[Changelog](CHANGELOG.md)** | **[Documentation](docs/)**

![PHP 8.4](docs/logo_php8_4.png)

## Description

This package provides strongly-typed classes for physical measurements (length, mass, time, temperature, etc.) with comprehensive unit conversion capabilities. The system uses a graph-based algorithm to automatically find conversion paths between units, supports SI metric and binary prefixes, and handles affine transformations for temperature scales.

Key capabilities include:

- **Type-safe measurements**: Each measurement type (Length, Mass, Time, etc.) is a separate class preventing accidental mixing
- **Automatic conversion**: Convert between any compatible units without manual conversion factors
- **Prefix support**: Full SI metric prefixes (quecto to quetta) and binary prefixes (Ki, Mi, Gi, etc.)
- **Arithmetic operations**: Add, subtract, multiply, and divide measurements with automatic unit handling
- **Flexible parsing**: Parse strings like "123.45 km", "90deg", or "25°C" into measurement objects
- **Part decomposition**: Break measurements into components (e.g., 12° 34′ 56″ or 1y 3mo 2d)

## Development and Quality Assurance / AI Disclosure

[Claude Chat](https://claude.ai) and [Claude Code](https://www.claude.com/product/claude-code) were used in the development of this package. The core classes were designed, coded, and commented primarily by the author, with Claude providing substantial assistance with code review, suggesting improvements, debugging, and generating tests and documentation. All code was thoroughly reviewed by the author, and validated using industry-standard tools including [PHP_Codesniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/), [PHPStan](https://phpstan.org/) (to level 9), and [PHPUnit](https://phpunit.de/index.html) to ensure full compliance with [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards and comprehensive unit testing with 100% code coverage. This collaborative approach resulted in a high-quality, thoroughly-tested, and well-documented package delivered in significantly less time than traditional development methods.

![Code Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)

## Requirements

- PHP ^8.4
- galaxon/core

## Installation

```bash
composer require galaxon/units
```

## Quick Start

```php
use Galaxon\Units\MeasurementTypes\Length;
use Galaxon\Units\MeasurementTypes\Temperature;
use Galaxon\Units\MeasurementTypes\Angle;

// Create measurements
$distance = new Length(5, 'km');
$temp = new Temperature(25, 'C');
$angle = new Angle(90, 'deg');

// Convert between units
$miles = $distance->to('mi');           // 3.10686... miles
$fahrenheit = $temp->to('F');           // 77°F
$radians = $angle->to('rad');           // 1.5707... rad

// Arithmetic operations
$total = $distance->add(new Length(500, 'm'));  // 5.5 km
$doubled = $distance->mul(2);                    // 10 km

// Parse from strings
$length = Length::parse('123.45 km');
$temp = Temperature::parse('98.6°F');
$angle = Angle::parse("45° 30' 15\"");

// Format as parts
$angle = new Angle(45.5042, 'deg');
echo $angle->formatParts('arcsec', 1);  // "45° 30′ 15.1″"
```

## Features

### Unit Conversion

The conversion system automatically finds paths between units using a graph-based algorithm. You only need to define direct conversions; indirect paths are computed automatically.

```php
// Direct conversion
$meters = new Length(1000, 'm');
$km = $meters->to('km');  // 1 km

// Indirect conversion (found automatically)
$feet = $meters->to('ft');  // 3280.84 ft
$miles = $meters->to('mi'); // 0.621371 mi
```

### Prefix Support

Units can accept SI metric prefixes, binary prefixes, or both:

```php
use Galaxon\Units\MeasurementTypes\Length;
use Galaxon\Units\MeasurementTypes\Memory;

// Metric prefixes (quecto to quetta)
$nano = new Length(500, 'nm');    // nanometres
$kilo = new Length(5, 'km');      // kilometres
$mega = new Length(1, 'Mm');      // megametres

// Binary prefixes for memory
$kibi = new Memory(1, 'KiB');     // 1024 bytes
$gibi = new Memory(1, 'GiB');     // 1073741824 bytes

// Mixed prefix support
$megabytes = new Memory(1, 'MB'); // 1000000 bytes (metric)
$mebibytes = new Memory(1, 'MiB'); // 1048576 bytes (binary)
```

### Temperature Conversions

Temperature uses affine transformations (y = mx + k) to handle non-proportional scales:

```php
use Galaxon\Units\MeasurementTypes\Temperature;

$celsius = new Temperature(0, 'C');
echo $celsius->to('F');  // 32°F
echo $celsius->to('K');  // 273.15K

$fahrenheit = new Temperature(212, 'F');
echo $fahrenheit->to('C');  // 100°C
```

### Arithmetic Operations

Measurements support addition, subtraction, multiplication, and division:

```php
$a = new Length(100, 'm');
$b = new Length(50, 'm');

$sum = $a->add($b);           // 150 m
$diff = $a->sub($b);          // 50 m
$scaled = $a->mul(2);         // 200 m
$halved = $a->div(2);         // 50 m
$abs = $diff->neg()->abs();   // 50 m

// Add with different units (auto-converted)
$total = $a->add(new Length(1, 'km'));  // 1100 m

// Convenience syntax
$total = $a->add(500, 'cm');  // 105 m
```

### Comparison and Approximate Equality

Compare measurements with exact or approximate equality:

```php
$a = new Length(1000, 'm');
$b = new Length(1, 'km');

// Exact comparison
$a->compare($b);        // 0 (equal)
$a->lessThan($b);       // false
$a->greaterThan($b);    // false

// Approximate comparison (handles floating-point precision)
$a->approxEqual($b);    // true

// Angles use radians for tolerance
$angle1 = new Angle(180, 'deg');
$angle2 = new Angle(M_PI, 'rad');
$angle1->approxEqual($angle2);  // true
```

### Part Decomposition

Break measurements into component parts:

```php
use Galaxon\Units\MeasurementTypes\Angle;
use Galaxon\Units\MeasurementTypes\Time;

// Angle to degrees, arcminutes, arcseconds
$angle = new Angle(45.5042, 'deg');
$parts = $angle->toPartsArray('arcsec', 2);
// ['sign' => 1, 'deg' => 45, 'arcmin' => 30, 'arcsec' => 15.12]

echo $angle->formatParts('arcsec', 1);  // "45° 30′ 15.1″"

// Create from parts
$angle = Angle::fromParts(45, 30, 15.12);

// Time to years, months, days, hours, minutes, seconds
$duration = new Time(90061, 's');
echo $duration->formatParts('s', 0);  // "1d 1h 1min 1s"

// Convert to DateInterval
$interval = $duration->toDateInterval();
```

### Trigonometric Functions

The Angle class provides trigonometric and hyperbolic functions:

```php
$angle = new Angle(45, 'deg');

// Trigonometric
$angle->sin();  // 0.7071...
$angle->cos();  // 0.7071...
$angle->tan();  // 1.0

// Reciprocal functions
$angle->sec();  // 1.4142...
$angle->csc();  // 1.4142...
$angle->cot();  // 1.0

// Hyperbolic
$angle->sinh();
$angle->cosh();
$angle->tanh();
```

## Classes

### Core Classes

#### [Measurement](docs/Measurement.md)

Abstract base class for all measurement types. Provides unit conversion, arithmetic operations, comparison, formatting, and part decomposition. Derived classes define their specific units and conversions.

#### [Unit](docs/Unit.md)

Represents a decomposed unit symbol (e.g., 'km2' → base 'm', prefix 'k', exponent 2). Handles prefix multipliers and exponent calculations.

#### [UnitConverter](docs/UnitConverter.md)

Manages unit conversions for a measurement type. Validates units and prefixes, stores conversion factors, and uses graph traversal to find indirect conversion paths.

#### [Conversion](docs/Conversion.md)

Represents an affine transformation (y = mx + k) for unit conversion. Tracks error scores to prefer shorter, more accurate conversion paths.

#### [FloatWithError](docs/FloatWithError.md)

A floating-point number with tracked absolute error. Propagates numerical errors through arithmetic operations to assess conversion precision.

### Measurement Types

#### [Angle](docs/Angle.md)

Angular measurements in radians, degrees, arcminutes, arcseconds, gradians, and turns. Includes trigonometric and hyperbolic functions, angle wrapping, and DMS formatting.

#### [Area](docs/Area.md)

Area measurements in square metres, hectares, acres, and imperial units (mi², yd², ft², in²).

#### [Length](docs/Length.md)

Length measurements in metres, imperial units (inches, feet, yards, miles), typography units (pixels, points), and astronomical units (au, ly, pc).

#### [Mass](docs/Mass.md)

Mass measurements in grams, tonnes, and imperial units (grains, ounces, pounds, stones, tons). Includes physical constants (electron, proton, neutron mass).

#### [Memory](docs/Memory.md)

Digital storage in bytes and bits with both metric (kB, MB, GB) and binary (KiB, MiB, GiB) prefixes.

#### [Temperature](docs/Temperature.md)

Temperature in Kelvin, Celsius, and Fahrenheit with proper affine conversions. Supports degree symbol notation (25°C).

#### [Time](docs/Time.md)

Time durations in seconds, minutes, hours, days, weeks, months, years, and centuries. Includes DateInterval conversion and part formatting.

#### [Volume](docs/Volume.md)

Volume measurements in cubic metres, litres, and imperial units (gallons, quarts, pints, cups, fluid ounces, tablespoons, teaspoons).

## Testing

The library includes comprehensive test coverage:

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test class
vendor/bin/phpunit tests/MeasurementTest.php

# Run with coverage (generates HTML report and clover.xml)
composer test
```

## License

MIT License - see [LICENSE](LICENSE) for details

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

For questions or suggestions, please [open an issue](https://github.com/mossy2100/PHP-Units/issues).

## Support

- **Issues**: https://github.com/mossy2100/PHP-Units/issues
- **Documentation**: See [docs/](docs/) directory for detailed class documentation
- **Examples**: See test files for comprehensive usage examples

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.
