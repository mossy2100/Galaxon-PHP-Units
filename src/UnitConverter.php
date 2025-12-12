<?php

declare(strict_types=1);

namespace Galaxon\Units;

use Galaxon\Core\Numbers;
use LogicException;
use ValueError;

/**
 * Manages unit conversions for a measurement type.
 *
 * This class handles:
 * - Validation of base units, prefixes, and conversion definitions
 * - Generation of prefixed units (e.g., 'km', 'mg', 'ms')
 * - Storage and retrieval of conversion factors between units
 * - Automatic discovery of indirect conversion paths via graph traversal
 * - Prefix algebra for converting between prefixed units
 *
 * The conversion system works by:
 * 1. Storing direct conversions provided in the configuration
 * 2. Automatically inferring additional conversions through:
 *    - Inversion (if a→b exists, compute b→a)
 *    - Composition (if a→c and c→b exist, compute a→b)
 * 3. Applying prefix adjustments when requested units have prefixes
 *
 * All conversions use the affine transformation formula: y = m*x + k
 * where m is the multiplier and k is the offset (typically 0 except for temperature).
 *
 * Error tracking: Each conversion has an error score based on numerical precision.
 * The system prefers shorter conversion paths with lower cumulative error.
 */
class UnitConverter
{
    // region Constants

    private const bool DEBUG = false;

    /**
     * Standard metric prefixes down to quecto (10^-30).
     *
     * Includes both standard symbols and alternatives (e.g., 'u' for micro).
     *
     * @var array<string, int|float>
     */
    public const array PREFIXES_SMALL_METRIC = [
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

    /**
     * Standard metric prefixes up to quetta (10^30).
     *
     * @var array<string, int|float>
     */
    public const array PREFIXES_LARGE_METRIC = [
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

    /**
     * Binary prefixes for memory measurements.
     *
     * @var array<string, int|float>
     */
    public const array PREFIXES_BINARY = [
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

    // endregion

    // region Instance properties

    /**
     * Derived units with codes indicating allowed prefixes.
     *
     * Keys are unit symbols (e.g., 'g', 's', 'K'). They may have exponents, e.g. 'm2'.
     * Values are integer codes indicating allowed prefixes.
     *
     * Example:
     * [
     *   'm' => Measurement::PREFIX_CODE_METRIC,  // metre with metric prefixes
     *   'ft' => 0,                               // foot with no prefixes
     * ]
     *
     * @var array<string, int>
     */
    private array $unitDefinitions;

    /**
     * Map of unit strings to objects, for convenient validation and lookup.
     * Keys are prefixed unit strings (e.g., 'cm3'). Values are Unit objects.
     *
     * @var array<string, Unit>
     */
    private(set) array $units;

    /**
     * Conversion matrix storing known conversions between units.
     *
     * Structure: $conversions[initUnit][finUnit] = Conversion
     * Includes both explicitly defined conversions and generated ones.
     *
     * @var array<string, array<string, Conversion>>
     */
    private array $conversions = [];

    /**
     * Original conversion definitions from configuration.
     *
     * Each element is an array: [initUnit, finUnit, multiplier, offset?]
     * Preserved for re-initialization when units or prefixes change.
     *
     * @var array<int, array{0: string, 1: string, 2: int|float, 3?: int|float}>
     */
    private array $conversionDefinitions;

    // endregion

    // region Constructor

    /**
     * Constructor.
     *
     * Initializes the unit conversion system with validation of all configuration.
     *
     * Validation includes:
     * - The $units array must be non-empty with string keys and int values.
     * - Conversion definitions must have 3-4 elements each.
     * - Unit symbols in conversions must be valid (base, derived, or prefixed units).
     * - Multipliers must be non-zero numbers.
     * - Offsets (if present) must be numbers.
     *
     * @param array<string, int> $units Units (base and derived only) with their prefix set code.
     * @param array<array{0: string, 1: string, 2: int|float, 3?: int|float}> $conversionDefinitions
     *      Conversion definitions.
     * @throws LogicException If any validation check fails.
     */
    public function __construct(array $units, array $conversionDefinitions)
    {
        // Validate the unit prefixes structure.
        if (empty($units)) {
            throw new LogicException('Units must be a non-empty array.');
        }

        // Store the base and derived units.
        $this->unitDefinitions = $units;

        // Generate the valid units.
        $this->resetUnits();

        // Check all conversions have the right structure.
        foreach ($conversionDefinitions as $convDefn) {
            // Validate the number of items in the conversion array.
            $nItems = count($convDefn);
            if ($nItems < 3 || $nItems > 4) {
                throw new LogicException('Each conversion must have 3 or 4 elements.');
            }

            // Validate the initial unit.
            if (!is_string($convDefn[0])) {
                throw new LogicException('Initial unit in conversion must be a string.');
            }
            if (!$this->isValidUnitSymbol($convDefn[0])) {
                throw new LogicException("Initial unit '{$convDefn[0]}' in conversion is not a valid " .
                                         'unit. Valid units include base, derived, and prefixed units.');
            }

            // Validate the final unit.
            if (!is_string($convDefn[1])) {
                throw new LogicException('Final unit in conversion must be a string.');
            }
            if (!$this->isValidUnitSymbol($convDefn[1])) {
                throw new LogicException("Final unit '{$convDefn[1]}' in conversion is not a valid " .
                                         'unit. Valid units include base, derived, and prefixed units.');
            }

            // Validate the multiplier (which must be positive).
            if (!Numbers::isNumber($convDefn[2])) {
                throw new LogicException('Multiplier in conversion must be a number (int or float).');
            }
            if (Numbers::equal($convDefn[2], 0)) {
                throw new LogicException('Multiplier in conversion cannot be zero.');
            }

            // Validate the optional offset (which can be negative).
            if ($nItems === 4 && !Numbers::isNumber($convDefn[3])) {
                throw new LogicException('Offset in conversion must be omitted, or a number (int or float).');
            }
        }

        // Store the conversion definitions.
        $this->conversionDefinitions = $conversionDefinitions;

        // Import the conversions.
        $this->resetConversions();

        // DEBUG
//        $this->completeMatrix();
    }

    // endregion

    // region Reset methods

    /**
     * Generate all valid prefixed units from base/derived units and their allowed prefixes.
     *
     * Populates the $validUnits cache by combining each base/derived unit with its allowed prefixes.
     * Called automatically during construction and when base/derived units are modified.
     *
     * @return void
     * @throws ValueError If any of the units or prefix set codes are invalid.
     */
    public function resetUnits(): void
    {
        $this->units = [];

        // Validate each unit and its prefix set.
        foreach ($this->unitDefinitions as $derived => $prefixSetCode) {
            // Check the derived unit is a string.
            if (!is_string($derived)) {
                throw new LogicException('Units must be strings.');
            }

            // Check the prefix set code is an integer in the valid range.
            if (!is_int($prefixSetCode) || $prefixSetCode < 0 || $prefixSetCode > Measurement::PREFIX_CODE_ALL) {
                throw new LogicException('Prefix set codes must be integers between 0 and ' .
                                         Measurement::PREFIX_CODE_ALL . '.');
            }

            // Add the unit without any prefix.
            $this->units[$derived] = new Unit($derived);

            // If the prefix set code is non-zero, add the unit with all possible prefixes.
            if ($prefixSetCode > 0) {
                $prefixes = self::getPrefixes($prefixSetCode);
                foreach ($prefixes as $prefix => $multiplier) {
                    $prefixed = $prefix . $derived;
                    $this->units[$prefixed] = new Unit($derived, $prefix, $multiplier);
                }
            }
        }
    }

    /**
     * Rebuild the conversion matrix from conversion definitions.
     *
     * Clears all existing conversions and recreates them from the stored definitions.
     * For prefixed unit conversions, also generates corresponding derived unit conversions needed by the pathfinding
     * algorithm.
     *
     * Called automatically when units or conversions are modified.
     *
     * @return void
     */
    private function resetConversions(): void
    {
        // Clear the conversion matrix.
        $this->conversions = [];

        // Initialize the conversion matrix from the supplied conversion definition arrays.
        // Note: Conversion definitions can contain base, derived, or prefixed units.
        foreach ($this->conversionDefinitions as $conversionDefinition) {
            // Deconstruct the conversion into local variables.
            [$initialUnit, $finalUnit, $multiplier] = $conversionDefinition;
            // The offset is optional, defaults to 0.
            $offset = $conversionDefinition[3] ?? 0;

            // Create and store the conversion as defined (may include prefixed units).
            $prefixedConversion = new Conversion($initialUnit, $finalUnit, $multiplier, $offset);
            $this->conversions[$initialUnit][$finalUnit] = $prefixedConversion;

            // DEBUG
            // @codeCoverageIgnoreStart
            if (self::DEBUG) {
                echo "New conversion from definition: $prefixedConversion\n";
            }
            // @codeCoverageIgnoreEnd

            // If either unit has a prefix, also store the conversion between derived units.
            // This is needed for the conversion generator algorithm.
            // Get the Unit objects.
            $initialUnitObject = $this->getUnit($initialUnit);
            $finalUnitObject = $this->getUnit($finalUnit);

            // Extract the derived unit symbols into convenient variables.
            $init = $initialUnitObject->derived;
            $fin = $finalUnitObject->derived;

            // Only create derived unit conversion if at least one unit has a prefix and derived units are different.
            if (
                !isset($this->conversions[$init][$fin])
                && ($initialUnitObject->prefix !== '' || $finalUnitObject->prefix !== '')
                && $init !== $fin
            ) {
                // Use removePrefixes() to create the derived unit conversion.
                // This ensures proper error management.
                $derivedConversion = $this->removePrefixes($prefixedConversion);

                // Store the derived unit conversion.
                $this->conversions[$init][$fin] = $derivedConversion;

                // DEBUG
                // @codeCoverageIgnoreStart
                if (self::DEBUG) {
                    echo "New conversion from definition: $derivedConversion\n";
                }
                // @codeCoverageIgnoreEnd
            }
        }
    }

    // endregion

    // region Methods for working with units and prefixes

    /**
     * Look up the Unit object corresponding to a unit symbol.
     *
     * @param string $unit The unit symbol.
     * @return Unit The Unit object corresponding to the provided string.
     * @throws ValueError If the unit is invalid.
     */
    public function getUnit(string $unit): Unit
    {
        $this->checkIsValidUnitSymbol($unit);
        return $this->units[$unit];
    }

    /**
     * Get all the valid unit symbols (derived and prefixed).
     *
     * @return string[] The valid unit symbols.
     */
    public function getUnitSymbols(): array
    {
        return array_keys($this->units);
    }

    /**
     * Compose a unit symbol from prefix, base unit, and exponent.
     *
     * This is a simple function. There's no validation of arguments, and it doesn't throw any exceptions.
     *
     * @param string $prefix The prefix symbol ('' for no prefix).
     * @param string $base The base unit symbol.
     * @param int $exp The exponent of the unit (default 1, which will not be included in the result).
     * @return string The composed unit symbol.
     *
     * @example
     *   composeUnit('k', 'm', 1)   // 'km'
     *   composeUnit('', 'm', 2)    // 'm2'
     *   composeUnit('c', 'm', 3)   // 'cm3'
     *   composeUnit('', 's', -2)   // 's-2'
     *   composeUnit('k', 'm', -1)  // 'km-1'
     */
    public function composeUnitSymbol(string $prefix, string $base, int $exp): string
    {
        return $prefix . $base . ($exp === 1 ? '' : $exp);
    }

    /**
     * Check if a unit symbol is valid. Includes prefixed, derived, and base units.
     *
     * @param string $unit The unit symbol.
     * @return bool True if the unit is valid.
     */
    public function isValidUnitSymbol(string $unit): bool
    {
        return array_key_exists($unit, $this->units);
    }

    /**
     * Validate a unit symbol and throw an exception if invalid.
     *
     * Provides a helpful error message listing valid units with and without prefix support.
     *
     * @param string $unit The unit symbol to validate.
     * @return void
     * @throws ValueError If the unit is not recognized.
     */
    public function checkIsValidUnitSymbol(string $unit): void
    {
        if (!$this->isValidUnitSymbol($unit)) {
            throw new ValueError("Invalid unit '$unit'.");
        }
    }

    /**
     * Return a set of prefixes, with multipliers, given an integer code comprising bitwise flags.
     *
     * This can be overridden in the derived class.
     *
     * @param int $prefixSetCode Code indicating the prefix sets to include.
     * @return array<string, int|float>
     */
    public static function getPrefixes(int $prefixSetCode = Measurement::PREFIX_CODE_ALL): array
    {
        // Cache the prefix sets.
        static $prefixCache = [];
        if (isset($prefixCache[$prefixSetCode])) {
            return $prefixCache[$prefixSetCode];
        }

        // Get the prefixes corresponding to this code.
        $prefixes = [];
        if ($prefixSetCode & Measurement::PREFIX_CODE_SMALL_METRIC) {
            $prefixes = array_merge($prefixes, self::PREFIXES_SMALL_METRIC);
        }
        if ($prefixSetCode & Measurement::PREFIX_CODE_LARGE_METRIC) {
            $prefixes = array_merge($prefixes, self::PREFIXES_LARGE_METRIC);
        }
        if ($prefixSetCode & Measurement::PREFIX_CODE_BINARY) {
            $prefixes = array_merge($prefixes, self::PREFIXES_BINARY);
        }

        // Remember this.
        $prefixCache[$prefixSetCode] = $prefixes;

        return $prefixes;
    }

    // endregion

    // region Methods for finding and doing conversions

    /**
     * Create a new conversion with different prefixes applied.
     *
     * Takes an existing conversion between units and adjusts the multiplier and offset to account for changing the
     * prefixes while keeping the derived units unchanged.
     *
     * Uses FloatWithError arithmetic to propagate error scores through the prefix adjustment calculation.
     *
     * @param Conversion $conversion The original conversion.
     * @param string $newInitialUnitPrefix The new initial unit prefix ('' for none).
     * @param string $newFinalUnitPrefix The new final unit prefix ('' for none).
     * @return Conversion A new conversion with adjusted parameters for the prefixed units.
     * @throws ValueError If either prefix is invalid.
     *
     * @example
     *   // Given conversion: m→ft with multiplier 3.28084
     *   // alterPrefixes(..., 'k', '') produces: km→ft with multiplier 3280.84
     */
    private function alterPrefixes(
        Conversion $conversion,
        string $newInitialUnitPrefix,
        string $newFinalUnitPrefix
    ): Conversion {
        // Get current units as objects.
        $currentInitialUnitObject = $this->getUnit($conversion->initialUnit);
        $currentFinalUnitObject = $this->getUnit($conversion->finalUnit);

        // Compose the new derived units.
        $newInitialUnit = $this->composeUnitSymbol(
            $newInitialUnitPrefix,
            $currentInitialUnitObject->base,
            $currentInitialUnitObject->exponent
        );
        $newFinalUnit = $this->composeUnitSymbol(
            $newFinalUnitPrefix,
            $currentFinalUnitObject->base,
            $currentFinalUnitObject->exponent
        );

        // Look up the unit objects for the new units. This will throw if either are invalid.
        $newInitialUnitObject = $this->getUnit($newInitialUnit);
        $newFinalUnitObject = $this->getUnit($newFinalUnit);

        // Calculate adjustments.
        $multiplierAdjustment = new FloatWithError(
            ($currentFinalUnitObject->multiplier * $newInitialUnitObject->multiplier) /
            ($newFinalUnitObject->multiplier * $currentInitialUnitObject->multiplier)
        );
        $offsetAdjustment = new FloatWithError($currentFinalUnitObject->multiplier / $newFinalUnitObject->multiplier);

        // Apply the adjustments to the multiplier and offset using FloatWithError for proper error tracking.
        $newMultiplier = $conversion->multiplier->mul($multiplierAdjustment);
        $newOffset = $conversion->offset->mul($offsetAdjustment);

        // Create and return the new conversion with updated units and multiplier.
        return new Conversion($newInitialUnit, $newFinalUnit, $newMultiplier, $newOffset);
    }

    /**
     * Generate a new conversion from an existing one by removing prefixes from the initial and final units, to get a
     * conversion between unprefixed derived units.
     *
     * @param Conversion $conversion The conversion that includes prefixed units.
     * @return Conversion New conversion between unprefixed (base or derived) units.
     */
    private function removePrefixes(Conversion $conversion): Conversion
    {
        return $this->alterPrefixes($conversion, '', '');
    }

    /**
     * Generate the next best conversion by traversing the conversion graph.
     *
     * Uses a best-first search strategy to find new conversions by:
     * - Inverting existing conversions (if a→b exists, compute b→a)
     * - Composing conversions through common units (if a→c and c→b exist, compute a→b)
     *
     * Selects the conversion with the lowest error score to add to the matrix.
     * Error scores guide the search toward shorter, more accurate paths.
     *
     * @return bool True if a new conversion was found and added, false if none remain.
     */
    private function generateNextConversion(): bool
    {
        $minErrScore = PHP_INT_MAX;
        $best = null;
        $units = array_keys($this->unitDefinitions);
        $initialUnit = '';
        $finalUnit = '';
        $commonUnit = '';

        // Test function. This will help us keep track of the best conversion found so far.
        $testNewConversion = static function (
            Conversion $conversion1,
            ?Conversion $conversion2,
            Conversion $newConversion,
            string $operation
        ) use (
            &$initialUnit,
            &$finalUnit,
            &$commonUnit,
            &$minErrScore,
            &$best
        ): bool {
            // Let's see if we have a new best.
            if ($newConversion->errorScore < $minErrScore) {
                $minErrScore = $newConversion->errorScore;
                $best = [
                    'initialUnit'   => $initialUnit,
                    'finalUnit'     => $finalUnit,
                    'commonUnit'    => $commonUnit,
                    'conversion1'   => $conversion1,
                    'conversion2'   => $conversion2,
                    'newConversion' => $newConversion,
                    'operation'     => $operation,
                    'errScore'      => $minErrScore,
                ];
                return true;
            }
            return false;
        };

        // Iterate through all possible pairs of derived units.
        foreach ($units as $initialUnit) {
            foreach ($units as $finalUnit) {
                // If this conversion is already known, continue.
                if ($initialUnit === $finalUnit || isset($this->conversions[$initialUnit][$finalUnit])) {
                    continue;
                }

                // Look for the inverse conversion.
                if (isset($this->conversions[$finalUnit][$initialUnit])) {
                    $conversion = $this->conversions[$finalUnit][$initialUnit];
                    $newConversion = $conversion->invert();
                    $testNewConversion($conversion, null, $newConversion, 'inversion');
                }

                // Look for a conversion opportunity via a common unit.
                /** @var string $commonUnit */
                foreach ($units as $commonUnit) {
                    // The common unit must be different from the initial and final units.
                    if ($initialUnit === $commonUnit || $finalUnit === $commonUnit) {
                        continue;
                    }

                    // Get conversions between the initial, final, and common unit.
                    $initialToCommon = $this->conversions[$initialUnit][$commonUnit] ?? null;
                    $commonToInitial = $this->conversions[$commonUnit][$initialUnit] ?? null;
                    $finalToCommon = $this->conversions[$finalUnit][$commonUnit] ?? null;
                    $commonToFinal = $this->conversions[$commonUnit][$finalUnit] ?? null;

                    // Combine initial->common with common->final (sequential).
                    if ($initialToCommon !== null && $commonToFinal !== null) {
                        $newConversion = $initialToCommon->combineSequential($commonToFinal);
                        $testNewConversion($initialToCommon, $commonToFinal, $newConversion, 'sequential combination');
                    }

                    // Combine initial->common with final->common (convergent).
                    if ($initialToCommon !== null && $finalToCommon !== null) {
                        $newConversion = $initialToCommon->combineConvergent($finalToCommon);
                        $testNewConversion($initialToCommon, $finalToCommon, $newConversion, 'convergent combination');
                    }

                    // Combine common->initial with common->final (divergent).
                    if ($commonToInitial !== null && $commonToFinal !== null) {
                        $newConversion = $commonToInitial->combineDivergent($commonToFinal);
                        $testNewConversion($commonToInitial, $commonToFinal, $newConversion, 'divergent combination');
                    }

                    // Combine common->initial with final->common (opposite).
                    if ($commonToInitial !== null && $finalToCommon !== null) {
                        $newConversion = $commonToInitial->combineOpposite($finalToCommon);
                        $testNewConversion($commonToInitial, $finalToCommon, $newConversion, 'opposite combination');
                    }
                }
            }
        }

        if ($best !== null) {
            // Store the best conversion we found for this scan.
            $this->conversions[$best['initialUnit']][$best['finalUnit']] = $best['newConversion'];

            // *********************************************************************************************************
            // DEBUGGING
            // @codeCoverageIgnoreStart
            if (self::DEBUG) {
                $description = "\nNew conversion for {$best['initialUnit']} to {$best['finalUnit']} found by " .
                               "{$best['operation']}:\n";
                if ($best['operation'] === 'inversion') {
                    $description .=
                        " Original conversion: {$best['conversion1']}\n" .
                        "      New conversion: {$best['newConversion']}\n";
                } else {
                    $description .=
                        "        Conversion 1: {$best['conversion1']}\n" .
                        "        Conversion 2: {$best['conversion2']}\n" .
                        "      New conversion: {$best['newConversion']}\n";
                }
                $description .= "      Absolute error: {$best['errScore']}\n";
                echo $description;
            }
            // @codeCoverageIgnoreEnd
            // *********************************************************************************************************

            return true;
        }

        return false;
    }

    /**
     * Get or compute the conversion between two units.
     *
     * Returns the Conversion object representing the transformation from initial to final unit.
     * The conversion may be:
     * - Retrieved from cache if previously computed
     * - A unity conversion if units are identical
     * - A prefix-only adjustment if units share the same base
     * - Generated by pathfinding through the conversion matrix
     *
     * Generated conversions are cached for future use.
     *
     * @param string $initialUnit The initial unit symbol.
     * @param string $finalUnit The final unit symbol.
     * @return Conversion The conversion transformation.
     * @throws ValueError If either unit is invalid.
     * @throws LogicException If no conversion path exists between the units.
     *
     * @example
     *   $conversion = $converter->getConversion('m', 'ft');
     *   $feet = $conversion->apply(10);  // Convert 10 meters to feet
     */
    public function getConversion(string $initialUnit, string $finalUnit): Conversion
    {
        // Check units are valid.
        $this->checkIsValidUnitSymbol($initialUnit);
        $this->checkIsValidUnitSymbol($finalUnit);

        // Handle the simple case.
        if ($initialUnit === $finalUnit) {
            return new Conversion($initialUnit, $finalUnit, 1);
        }

        // See if we already have this one.
        if (isset($this->conversions[$initialUnit][$finalUnit])) {
            return $this->conversions[$initialUnit][$finalUnit];
        }

        // Get the Unit objects.
        $initialUnitObject = $this->getUnit($initialUnit);
        $finalUnitObject = $this->getUnit($finalUnit);

        // Extract the derived unit symbols into convenient variables.
        $init = $initialUnitObject->derived;
        $fin = $finalUnitObject->derived;

        if ($init === $fin) {
            // Converting between two units with the same derived unit. Since they are different, they must have
            // different prefixes, or one has a prefix and one doesn't. Start with the unity conversion.
            $conversion = new Conversion($init, $fin, 1);
        } elseif (isset($this->conversions[$init][$fin])) {
            // Check if the conversion between derived units is already known.
            $conversion = $this->conversions[$init][$fin];
        } else {
            // DEBUG
            // @codeCoverageIgnoreStart
            if (self::DEBUG) {
                echo "SEARCH FOR CONVERSION BETWEEN '$init' AND '$fin'\n";
            }
            // @codeCoverageIgnoreEnd

            // Keep generating new conversions until we find the conversion between the derived units, or we run
            // out of options.
            do {
                $result = $this->generateNextConversion();
            } while (!isset($this->conversions[$init][$fin]) && $result);

            // If we didn't find the conversion, throw an exception.
            // This indicates either a problem in the setup of the Measurement-derived class, or the programmer has
            // added or removed needed conversions. So, throw a LogicException.
            if (!isset($this->conversions[$init][$fin])) {
                throw new LogicException("No conversion between '$initialUnit' and '$finalUnit' could be found.");
            }

            // Update the matrix.
            $conversion = $this->conversions[$init][$fin];
        }

        // If there are no prefixes, done.
        if ($initialUnitObject->prefix === '' && $finalUnitObject->prefix === '') {
            return $conversion;
        }

        // Apply prefixes.
        $conversion = $this->alterPrefixes($conversion, $initialUnitObject->prefix, $finalUnitObject->prefix);

        // Cache and return the new conversion.
        $this->conversions[$initialUnit][$finalUnit] = $conversion;

        return $conversion;
    }

    /**
     * Convert a numeric value from one unit to another.
     *
     * Validates both unit symbols, retrieves or computes the conversion,
     * and applies it to the value using the formula: y = m*x + k
     *
     * @param float $value The value to convert.
     * @param string $initialUnit The initial unit symbol.
     * @param string $finalUnit The final unit symbol.
     * @return float The converted value.
     * @throws ValueError If either unit symbol is invalid.
     * @throws LogicException If no conversion path exists between the units.
     *
     * @example
     *   $meters = 100;
     *   $feet = $converter->convert($meters, 'm', 'ft');  // 328.084
     */
    public function convert(float $value, string $initialUnit, string $finalUnit): float
    {
        // Check units are valid.
        $this->checkIsValidUnitSymbol($initialUnit);
        $this->checkIsValidUnitSymbol($finalUnit);

        // Get the conversion and convert the value. y = mx + k
        return $this->getConversion($initialUnit, $finalUnit)->apply($value)->value;
    }

    // endregion

    // region Dynamic modification methods

    /**
     * Add or update a base/derived unit in the system.
     *
     * Triggers regeneration of prefixed units and conversion matrix.
     *
     * @param string $unit The unit symbol to add.
     * @param int $prefixSetCode Code indicating allowed prefixes for this unit (e.g. Measurement::PREFIX_CODE_METRIC).
     * @return void
     */
    public function addUnit(string $unit, int $prefixSetCode): void
    {
        $this->unitDefinitions[$unit] = $prefixSetCode;
        $this->resetUnits();
        $this->resetConversions();
    }

    /**
     * Remove a base/derived unit from the system.
     *
     * Triggers regeneration of prefixed units and conversion matrix.
     *
     * @param string $unit The unit symbol to remove.
     * @return void
     */
    public function removeUnit(string $unit): void
    {
        unset($this->unitDefinitions[$unit]);
        $this->resetUnits();
        $this->resetConversions();
    }

    /**
     * Add or update a conversion definition.
     *
     * If a conversion between the same units already exists, it will be updated. Otherwise, a new conversion is added.
     *
     * Triggers rebuilding of the conversion matrix.
     *
     * @param string $initialUnit The initial unit symbol. Can be a prefixed, derived, or base unit.
     * @param string $finalUnit The final unit symbol. Can be a prefixed, derived, or base unit.
     * @param int|float $multiplier The scale factor (cannot be 0).
     * @param int|float $offset The additive offset (default 0).
     * @return void
     */
    public function addConversion(
        string $initialUnit,
        string $finalUnit,
        int|float $multiplier,
        int|float $offset = 0
    ): void {
        // Ensure multiplier is not zero.
        if (Numbers::equal($multiplier, 0)) {
            throw new ValueError('Multiplier cannot be zero.');
        }

        // Find if this conversion already exists.
        /** @var null|string $key */
        $key = array_find_key(
            $this->conversionDefinitions,
            static fn ($conversion) => $conversion[0] === $initialUnit && $conversion[1] === $finalUnit
        );

        if ($key !== null) {
            // Update existing conversion.
            $this->conversionDefinitions[$key][2] = $multiplier;
            $this->conversionDefinitions[$key][3] = $offset;
        } else {
            // Add new conversion.
            $this->conversionDefinitions[] = [$initialUnit, $finalUnit, $multiplier, $offset];
        }

        $this->resetConversions();
    }

    /**
     * Remove a conversion definition.
     *
     * Triggers rebuilding of the conversion matrix.
     *
     * @param string $initialUnit The initial unit symbol.
     * @param string $finalUnit The final unit symbol.
     * @return void
     */
    public function removeConversion(string $initialUnit, string $finalUnit): void
    {
        $this->conversionDefinitions = array_filter(
            $this->conversionDefinitions,
            static fn ($conversion) => !($conversion[0] === $initialUnit && $conversion[1] === $finalUnit)
        );
        $this->resetConversions();
    }

    // endregion

    // region Matrix-level methods

    /**
     * Generate all possible conversions by exhaustive graph traversal.
     *
     * Repeatedly calls generateNextConversion() until no more conversions can be found.
     * This creates a complete conversion matrix for all unit pairs.
     *
     * Note: This method is primarily for debugging. Normal operation uses lazy generation
     * in getConversion(), which is more efficient as it only computes needed conversions.
     *
     * @return void
     * @codeCoverageIgnore
     */
    public function completeMatrix(): void
    {
        do {
            $result = $this->generateNextConversion();
        } while ($result);
    }

    /**
     * Check if the conversion matrix is complete (all conversions between derived units are known).
     *
     * @return bool True if complete, false otherwise.
     * @codeCoverageIgnore
     */
    public function isMatrixComplete(): bool
    {
        $units = array_keys($this->unitDefinitions);
        foreach ($units as $initialUnit) {
            foreach ($units as $finalUnit) {
                if (!isset($this->conversions[$initialUnit][$finalUnit])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Print the conversion matrix for debugging purposes.
     *
     * @return void
     * @codeCoverageIgnore
     */
    public function printMatrix(): void
    {
        $colWidth = 20;
        $units = array_keys($this->unitDefinitions);

        echo '+------+';
        foreach ($units as $baseUnit) {
            echo str_repeat('-', $colWidth) . '+';
        }
        echo "\n";

        echo '|      |';
        foreach ($units as $baseUnit) {
            echo str_pad($baseUnit, $colWidth, ' ', STR_PAD_BOTH) . '|';
        }
        echo "\n";

        echo '+------+';
        foreach ($units as $baseUnit) {
            echo str_repeat('-', $colWidth) . '+';
        }
        echo "\n";

        foreach ($units as $initialUnit) {
            echo '|' . str_pad($initialUnit, 6) . '|';
            foreach ($units as $finalUnit) {
                if (isset($this->conversions[$initialUnit][$finalUnit])) {
                    $mul = $this->conversions[$initialUnit][$finalUnit]->multiplier->value;
                    $sMul = sprintf('%.10g', $mul);
                    echo str_pad($sMul, $colWidth);
                } else {
                    echo str_pad('?', $colWidth);
                }
                echo '|';
            }
            echo "\n";
        }

        echo '+------+';
        foreach ($units as $baseUnit) {
            echo str_repeat('-', $colWidth) . '+';
        }
        echo "\n";
    }

    /**
     * Dump the conversion matrix contents for debugging purposes.
     *
     * @return void
     * @codeCoverageIgnore
     */
    public function dumpMatrix(): void
    {
        echo "\n";
        echo "CONVERSION MATRIX\n";
        foreach ($this->conversions as $initialUnit => $conversions) {
            foreach ($conversions as $finalUnit => $conversion) {
                echo "$conversion\n";
            }
        }
        echo "\n";
        echo "\n";
    }

    // endregion
}
