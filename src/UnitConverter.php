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
    private const bool DEBUG = false;

    // region Instance properties

    /**
     * Derived units with codes indicating allowed prefixes.
     *
     * Keys are unit symbols (e.g., 'g', 's', 'K'). They may have exponents, e.g. 'm2'.
     * Values are integer codes indicating allowed prefixes.
     *
     * Example:
     * [
     *   'm' => Measurement::PREFIXES_METRIC,  // metre with metric prefixes
     *   'ft' => 0,                               // foot with no prefixes
     * ]
     *
     * @var array<string, int>
     */
    private array $units;

    /**
     * Array of all prefixes and their multipliers.
     *
     * @var array<string, int|float>
     */
    private array $prefixes;

    /**
     * Cached map of prefixed units to their components.
     *
     * Keys are prefixed unit strings (e.g., 'km', 'mg').
     * Values are arrays: [prefix, baseUnit] (e.g., ['k', 'm']).
     *
     * Generated automatically by combining prefixes with prefix-capable base units.
     *
     * @var array<string, string[]>
     */
    private array $prefixedUnits;

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
     * @var array<int, array{0: string, 1: string, 2: int|float, 3?: int|float}>>
     */
    private array $conversionDefinitions = [];

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
     * @param array<string, int> $units Units with their prefix group code.
     * @param array<int, array<int, array{0: string, 1: string, 2: int|float, 3?: int|float}>> $conversionDefinitions
     *      Conversion definitions.
     * @throws LogicException If any validation check fails.
     */
    public function __construct(array $units, array $prefixes, array $conversionDefinitions)
    {
        // Validate the unit prefixes structure.
        if (empty($units)) {
            throw new LogicException('Base units must be a non-empty array.');
        }

        // Validate each unit and its prefix set.
        foreach ($units as $unit => $prefixGroupCode) {
            if (!is_string($unit)) {
                throw new LogicException('All units must be strings.');
            }
            if (!is_int($prefixGroupCode)) {
                throw new LogicException('All prefix group codes must be integers.');
            }
        }

        // Store the base units.
        $this->units = $units;

        // Generate the units with prefixes.
        $this->resetPrefixedUnits();

        // Validate the prefixes array.
        foreach ($prefixes as $prefix => $multiplier) {
            if (!is_string($prefix)) {
                throw new LogicException('All prefixes must be strings.');
            }
            if (!Numbers::isNumber($multiplier)) {
                throw new LogicException('All prefix multipliers must be numbers.');
            }
        }

        // Store the prefixes.
        $this->prefixes = $prefixes;

        // Get all valid units (base, derived, and prefixed) for validation.
        $validUnits = $this->getValidUnits();

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
            if (!in_array($convDefn[0], $validUnits, true)) {
                throw new LogicException("Initial unit '{$convDefn[0]}' in conversion is not a valid " .
                                         'unit. Valid units include base, derived, and prefixed units.');
            }

            // Validate the final unit.
            if (!is_string($convDefn[1])) {
                throw new LogicException('Final unit in conversion must be a string.');
            }
            if (!in_array($convDefn[1], $validUnits, true)) {
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
     * Generate all valid prefixed units from base units and their allowed prefixes.
     *
     * Populates the $prefixedUnits cache by combining each base unit with its allowed prefixes.
     * Called automatically when base units or prefixes are modified.
     *
     * @return void
     */
    public function resetPrefixedUnits(): void
    {
        $this->prefixedUnits = [];
        foreach ($this->units as $baseUnit => $prefixSetCode) {
            $prefixes = Measurement::getPrefixes($prefixSetCode);
            foreach ($prefixes as $prefix => $multiplier) {
                $this->prefixedUnits[$prefix . $baseUnit] = [$prefix, $baseUnit];
            }
        }
    }

    /**
     * Rebuild the conversion matrix from conversion definitions.
     *
     * Clears all existing conversions and recreates them from the stored definitions.
     * For prefixed unit conversions, also generates corresponding base unit conversions
     * needed by the pathfinding algorithm.
     *
     * Called automatically when units, prefixes, or conversions are modified.
     *
     * @return void
     */
    private function resetConversions(): void
    {
        // Clear the conversion matrix.
        $this->conversions = [];

        // Initialize the conversion matrix from the supplied conversion definition arrays.
        // Note: Conversion definitions can now contain base units or units with prefixes.
        foreach ($this->conversionDefinitions as $conversionDefinition) {
            // Deconstruct the conversion into local variables.
            [$initUnit, $finUnit, $multiplier] = $conversionDefinition;
            // The offset is optional, defaults to 0.
            $offset = $conversionDefinition[3] ?? 0;

            // Create and store the conversion as defined (may include prefixed units).
            $prefixedConversion = new Conversion($initUnit, $finUnit, $multiplier, $offset);
            $this->conversions[$initUnit][$finUnit] = $prefixedConversion;
            if (self::DEBUG) {
                echo "New conversion from definition: $prefixedConversion\n";
            }

            // If either unit has a prefix, also store the conversion between base units.
            // This is needed for the conversion generator algorithm.
            [$initPrefix, $initBase] = $this->decomposeUnit($initUnit);
            [$finPrefix, $finBase] = $this->decomposeUnit($finUnit);

            // Only create base conversion if at least one unit has a prefix and base units are different.
            if (
                !isset($this->conversions[$initBase][$finBase]) && ($initPrefix !== null || $finPrefix !== null) &&
                $initBase !== $finBase
            ) {
                // Use alterPrefixes() to create the base conversion (no prefixes).
                // This ensures proper error management.
                $baseConversion = $this->alterPrefixes($prefixedConversion, null, null);

                // Store the base unit conversion.
                $this->conversions[$initBase][$finBase] = $baseConversion;
                if (self::DEBUG) {
                    echo "New conversion from definition: $baseConversion\n";
                }
            }
        }
    }

    // endregion

    // region Methods for working with units

    /**
     * Get all valid unit symbols.
     *
     * Returns both base units and all prefixed variations.
     *
     * @return string[] Array of valid unit symbols.
     *
     * @example
     *   // If base units are ['m', 'ft'] and 'm' can have prefixes ['k', 'c']
     *   // Returns: ['m', 'ft', 'km', 'cm']
     */
    public function getValidUnits(): array
    {
        return array_merge(array_keys($this->units), array_keys($this->prefixedUnits));
    }

    /**
     * Decompose a unit symbol into prefix, base unit, and exponent.
     *
     * If there's no prefix, the prefix will be null.
     * If there's no exponent, the exponent will be null.
     *
     * @param string $unit The unit symbol to decompose.
     * @return array{0: ?string, 1: string, 2: ?int} Tuple of [prefix, baseUnit, exponent].
     *
     * @example
     *   getUnitComponents('km')   // ['k',  'm',  1]
     *   getUnitComponents('m')    // [null, 'm',  1]
     *   getUnitComponents('ft')   // [null, 'ft', 1]
     *   getUnitComponents('in2')  // [null, 'in', 2]
     *   getUnitComponents('cm3')  // ['c',  'm',  3]
     */
    public function decomposeUnit(string $unit): array
    {
        $this->checkUnitIsValid($unit);

        // Get the prefixed or base unit.
        if (isset($this->prefixedUnits[$unit])) {
            [$prefix, $baseUnit] = $this->prefixedUnits[$unit];
        } else {
            $prefix = null;
            $baseUnit = $unit;
        }

        // Get exponent if present. Only one digit supported. Must be a digit between 2 and 9.
        $lastChar = substr($unit, -1);
        if ($lastChar >= '2' && $lastChar <= '9') {
            $exp = (int)$lastChar;
            $baseUnit = substr($unit, 0, -1);
        } else {
            // Default to 1 if no exponent digit is present.
            $exp = null;
        }

        return [$prefix, $baseUnit, $exp];
    }

    /**
     * Compose a unit symbol from prefix, base unit, and exponent.
     *
     * @param ?string $prefix The prefix symbol (or null for no prefix).
     * @param string $baseUnit The base unit symbol.
     * @param ?int $exponent The exponent of the unit (default null).
     * @return string
     */
    public function composeUnit(?string $prefix, string $baseUnit, ?int $exponent): string
    {
        return $prefix . $baseUnit . $exponent;
    }

    /**
     * Get the multiplier for a prefix.
     *
     * @param ?string $prefix The prefix symbol (or null for no prefix).
     * @param int $exponent The exponent of the unit (default 1).
     * @return float The multiplier.
     * @throws ValueError If the prefix or exponent is invalid.
     */
    private function getPrefixMultiplier(?string $prefix, int $exponent = 1): float
    {
        // Validate prefix.
        if ($prefix !== null && !isset($this->prefixes[$prefix])) {
            throw new ValueError("Prefix '{$prefix}' is not a valid prefix.");
        }

        // Validate exponent.
        if ($exponent < 1 || $exponent > 9) {
            throw new ValueError('Exponent must be between 1 and 9 (inclusive).');
        }

        // Return the multiplier.
        return ($this->prefixes[$prefix] ?? 1) ** $exponent;
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
    public function checkUnitIsValid(string $unit): void
    {
        if (!in_array($unit, $this->getValidUnits(), true)) {
            $quotedBaseUnits = array_map(static fn($unit) => "'$unit'", array_keys($this->units));
            $strUnits = implode(', ', $quotedBaseUnits);
            throw new ValueError("Invalid unit '$unit'. Valid base units: $strUnits. " .
                                 'Some units may be used with a metric or binary prefix.');
        }
    }

    // endregion

    // region Methods for finding and doing conversions

    /**
     * Create a new conversion with different prefixes applied.
     *
     * Takes an existing conversion between units and adjusts the multiplier and offset
     * to account for changing the prefixes while keeping the base units unchanged.
     *
     * Uses FloatWithError arithmetic to properly propagate error scores through
     * the prefix adjustment calculation.
     *
     * @param Conversion $conversion The original conversion.
     * @param ?string $newInitPrefix The desired initial unit prefix (null for no prefix).
     * @param ?string $newFinPrefix The desired final unit prefix (null for no prefix).
     * @return Conversion A new conversion with adjusted parameters for the prefixed units.
     * @throws ValueError If either prefix is invalid.
     *
     * @example
     *   // Given conversion: m→ft with multiplier 3.28084
     *   // alterPrefixes(..., 'k', null) produces: km→ft with multiplier 3280.84
     */
    private function alterPrefixes(Conversion $conversion, ?string $newInitPrefix, ?string $newFinPrefix): Conversion
    {
        // Decompose current units.
        [$curInitPrefix, $curInitBase, $curInitExp] = $this->decomposeUnit($conversion->initialUnit);
        [$curFinPrefix, $curFinBase, $curFinExp] = $this->decomposeUnit($conversion->finalUnit);

        // Get current prefix multipliers (default to 1.0 if no prefix).
        $curInitMultiplier = $this->getPrefixMultiplier($curInitPrefix, $curInitExp);
        $curFinMultiplier = $this->getPrefixMultiplier($curFinPrefix, $curFinExp);

        // Get new prefix multipliers.
        $newInitMultiplier = $this->getPrefixMultiplier($newInitPrefix, $curInitExp);
        $newFinMultiplier = $this->getPrefixMultiplier($newFinPrefix, $curFinExp);

        // Calculate adjustments.
        $multiplierAdjustment = new FloatWithError(($curFinMultiplier * $newInitMultiplier) /
                                                   ($newFinMultiplier * $curInitMultiplier));
        $offsetAdjustment = new FloatWithError($curFinMultiplier / $newFinMultiplier);

        // Apply the adjustments to the multiplier and offset using FloatWithError for proper error tracking.
        $newMultiplier = $conversion->multiplier->mul($multiplierAdjustment);
        $newOffset = $conversion->offset->mul($offsetAdjustment);

        // Compose the new units.
        $newInitUnit = $this->composeUnit($newInitPrefix, $curInitBase, $curInitExp);
        $newFinUnit = $this->composeUnit($newFinPrefix, $curFinBase, $curFinExp);

        // Create and return the new conversion with updated units and multiplier.
        return new Conversion($newInitUnit, $newFinUnit, $newMultiplier, $newOffset);
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
     * @param ?string $initSearch Optional target initial unit (unused, for future optimization).
     * @param ?string $finSearch Optional target final unit (unused, for future optimization).
     * @return bool True if a new conversion was found and added, false if none remain.
     */
    private function generateNextConversion(?string $initSearch = null, ?string $finSearch = null): bool
    {
        $minErrScore = PHP_INT_MAX;
        $best = null;
        $baseUnits = array_keys($this->units);
        $initUnit = '';
        $finUnit = '';
        $commonUnit = '';

        // Test function. This will help us keep track of the best conversion found so far.
        $testNewConversion = function (
            Conversion $conversion1,
            ?Conversion $conversion2,
            Conversion $newConversion,
            string $operation
        ) use (
            &$initUnit,
            &$finUnit,
            &$commonUnit,
            &$minErrScore,
            &$best
        ): bool {
            // Let's see if we have a new best.
            if ($newConversion->errorScore < $minErrScore) {
                $minErrScore = $newConversion->errorScore;
                $best = [
                    'initialUnit'   => $initUnit,
                    'finalUnit'     => $finUnit,
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

        // Iterate through all possible pairs of base units.
        foreach ($baseUnits as $initUnit) {
            foreach ($baseUnits as $finUnit) {
                // If this conversion is already known, continue.
                if ($initUnit === $finUnit || isset($this->conversions[$initUnit][$finUnit])) {
                    continue;
                }

                // Look for the inverse conversion.
                if (isset($this->conversions[$finUnit][$initUnit])) {
                    $conversion = $this->conversions[$finUnit][$initUnit];
                    $newConversion = $conversion->invert();
                    $testNewConversion($conversion, null, $newConversion, 'inversion');
                }

                // Look for a conversion opportunity via a common unit.
                /** @var string $commonUnit */
                foreach ($baseUnits as $commonUnit) {
                    // The common unit must be different from the initial and final units.
                    if ($initUnit === $commonUnit || $finUnit === $commonUnit) {
                        continue;
                    }

                    // Get conversions between the initial, final, and common unit.
                    $initToCommon = $this->conversions[$initUnit][$commonUnit] ?? null;
                    $commonToInit = $this->conversions[$commonUnit][$initUnit] ?? null;
                    $finToCommon = $this->conversions[$finUnit][$commonUnit] ?? null;
                    $commonToFin = $this->conversions[$commonUnit][$finUnit] ?? null;

                    // Combine initial->common with common->final (sequential).
                    if ($initToCommon !== null && $commonToFin !== null) {
                        $newConversion = $initToCommon->combineSequential($commonToFin);
                        $testNewConversion($initToCommon, $commonToFin, $newConversion, 'sequential combination');
                    }

                    // Combine initial->common with final->common (convergent).
                    if ($initToCommon !== null && $finToCommon !== null) {
                        $newConversion = $initToCommon->combineConvergent($finToCommon);
                        $testNewConversion($initToCommon, $finToCommon, $newConversion, 'convergent combination');
                    }

                    // Combine common->initial with common->final (divergent).
                    if ($commonToInit !== null && $commonToFin !== null) {
                        $newConversion = $commonToInit->combineDivergent($commonToFin);
                        $testNewConversion($commonToInit, $commonToFin, $newConversion, 'divergent combination');
                    }

                    // Combine common->initial with final->common (opposite).
                    if ($commonToInit !== null && $finToCommon !== null) {
                        $newConversion = $commonToInit->combineOpposite($finToCommon);
                        $testNewConversion($commonToInit, $finToCommon, $newConversion, 'opposite combination');
                    }
                }
            }
        }

        if ($best !== null) {
            // Store the best conversion we found for this scan.
            $this->conversions[$best['initialUnit']][$best['finalUnit']] = $best['newConversion'];

            // *********************************************************************************************************
            // DEBUGGING
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
            // *********************************************************************************************************

            return true;
        }

        return false;
    }

    /**
     * Get or compute the conversion between two units.
     *
     * Returns the Conversion object representing the transformation from initUnit to finUnit.
     * The conversion may be:
     * - Retrieved from cache if previously computed
     * - A unity conversion if units are identical
     * - A prefix-only adjustment if units share the same base
     * - Generated by pathfinding through the conversion graph
     *
     * Generated conversions are cached for future use.
     *
     * @param string $initUnit The source unit symbol.
     * @param string $finUnit The target unit symbol.
     * @return Conversion The conversion transformation.
     * @throws ValueError If either unit is invalid.
     * @throws LogicException If no conversion path exists between the units.
     *
     * @example
     *   $conversion = $converter->getConversion('m', 'ft');
     *   $feet = $conversion->apply(10);  // Convert 10 meters to feet
     */
    public function getConversion(string $initUnit, string $finUnit): Conversion
    {
        // Check units are valid.
        $this->checkUnitIsValid($initUnit);
        $this->checkUnitIsValid($finUnit);

        // Handle simple case.
        if ($initUnit === $finUnit) {
            return new Conversion($initUnit, $finUnit, 1);
        }

        // See if we already have this one.
        if (isset($this->conversions[$initUnit][$finUnit])) {
            return $this->conversions[$initUnit][$finUnit];
        }

        // Break down the units into prefixes and base units.
        [$initPrefix, $initBase] = $this->decomposeUnit($initUnit);
        [$finPrefix, $finBase] = $this->decomposeUnit($finUnit);

        if ($initBase === $finBase) {
            // Simply converting between two units with the same base unit. Since they are different, they must have
            // different prefixes, or one has a prefix and one doesn't. Start with the unity conversion.
            $conversion = new Conversion($initBase, $finBase, 1);
        } elseif (isset($this->conversions[$initBase][$finBase])) {
            // Check if the conversion between base units is already known.
            $conversion = $this->conversions[$initBase][$finBase];
        } else {
            // Keep generating new conversions until we find the conversion between the base units, or we run
            // out of options.
            if (self::DEBUG) {
                echo "SEARCH FOR CONVERSION BETWEEN '$initBase' AND '$finBase'\n";
            }
            do {
                $result = $this->generateNextConversion($initBase, $finBase);
            } while (!isset($this->conversions[$initBase][$finBase]) && $result);

            // If we didn't find the conversion, throw an exception.
            // This indicates either a problem in the setup of the Measurement-derived class, or the programmer has
            // added or removed needed conversions. So, throw a LogicException.
            if (!isset($this->conversions[$initBase][$finBase])) {
                throw new LogicException("No conversion between '$initUnit' and '$finUnit' could be found.");
            }

            $conversion = $this->conversions[$initBase][$finBase];
        }

        // If there are no prefixes, done.
        if ($initPrefix === null && $finPrefix === null) {
            return $conversion;
        }

        // Apply prefixes.
        $conversion = $this->alterPrefixes($conversion, $initPrefix, $finPrefix);

        // Cache and return the new conversion.
        $this->conversions[$initUnit][$finUnit] = $conversion;
        return $conversion;
    }

    /**
     * Convert a numeric value from one unit to another.
     *
     * Validates both unit symbols, retrieves or computes the conversion,
     * and applies it to the value using the formula: y = m*x + k
     *
     * @param float $value The value to convert.
     * @param string $initUnit The source unit symbol.
     * @param string $finUnit The target unit symbol.
     * @return float The converted value.
     * @throws ValueError If either unit symbol is invalid.
     * @throws LogicException If no conversion path exists between the units.
     *
     * @example
     *   $meters = 100;
     *   $feet = $converter->convert($meters, 'm', 'ft');  // 328.084
     */
    public function convert(float $value, string $initUnit, string $finUnit): float
    {
        // Check units are valid.
        $this->checkUnitIsValid($initUnit);
        $this->checkUnitIsValid($finUnit);

        // Get the conversion and convert the value. y = mx + k
        return $this->getConversion($initUnit, $finUnit)->apply($value)->value;
    }

    // endregion

    // region Dynamic modification methods

    /**
     * Add or update a base unit in the system.
     *
     * Triggers regeneration of prefixed units and conversion matrix.
     *
     * @param string $unit The unit symbol to add.
     * @param int $prefixSetCode Code indicating allowed prefixes for this unit (e.g. Measurement::PREFIXES_METRIC).
     * @return void
     */
    public function addBaseUnit(string $unit, int $prefixSetCode): void
    {
        $this->units[$unit] = $prefixSetCode;
        $this->resetPrefixedUnits();
        $this->resetConversions();
    }

    /**
     * Remove a base unit from the system.
     *
     * Triggers regeneration of prefixed units and conversion matrix.
     *
     * @param string $unit The unit symbol to remove.
     * @return void
     */
    public function removeBaseUnit(string $unit): void
    {
        unset($this->units[$unit]);
        $this->resetPrefixedUnits();
        $this->resetConversions();
    }

    /**
     * Add or update a prefix in the system.
     *
     * Triggers regeneration of prefixed units and conversion matrix.
     *
     * @param string $prefix The prefix symbol to add.
     * @param int|float $multiplier The multiplier for this prefix.
     * @return void
     */
    public function addPrefix(string $prefix, int|float $multiplier): void
    {
        $this->prefixes[$prefix] = $multiplier;
        $this->resetPrefixedUnits();
        $this->resetConversions();
    }

    /**
     * Remove a prefix from the system.
     *
     * Triggers regeneration of prefixed units and conversion matrix.
     *
     * @param string $prefix The prefix symbol to remove.
     * @return void
     */
    public function removePrefix(string $prefix): void
    {
        unset($this->prefixes[$prefix]);
        $this->resetPrefixedUnits();
        $this->resetConversions();
    }

    /**
     * Add or update a conversion definition.
     *
     * If a conversion between the same units already exists, it will be updated.
     * Otherwise, a new conversion is added.
     *
     * Triggers rebuilding of the conversion matrix.
     *
     * @param string $initUnit The source unit symbol.
     * @param string $finUnit The target unit symbol.
     * @param int|float $multiplier The scale factor.
     * @param int|float $offset The additive offset (default 0).
     * @return void
     */
    public function addConversion(string $initUnit, string $finUnit, int|float $multiplier, int|float $offset = 0): void
    {
        // Find if this conversion already exists.
        /** @var null|string $key */
        $key = array_find_key(
            $this->conversionDefinitions,
            static fn($conversion) => $conversion[0] === $initUnit && $conversion[1] === $finUnit
        );

        if ($key !== null) {
            // Update existing conversion.
            $this->conversionDefinitions[$key][2] = $multiplier;
            $this->conversionDefinitions[$key][3] = $offset;
        } else {
            // Add new conversion.
            $this->conversionDefinitions[] = [$initUnit, $finUnit, $multiplier, $offset];
        }

        $this->resetConversions();
    }

    /**
     * Remove a conversion definition.
     *
     * Triggers rebuilding of the conversion matrix.
     *
     * @param string $initUnit The source unit symbol.
     * @param string $finUnit The target unit symbol.
     * @return void
     */
    public function removeConversion(string $initUnit, string $finUnit): void
    {
        $this->conversionDefinitions = array_filter(
            $this->conversionDefinitions,
            static fn($conversion) => !($conversion[0] === $initUnit && $conversion[1] === $finUnit)
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
     */
    public function completeMatrix()
    {
        do {
            $result = $this->generateNextConversion();
        } while ($result);
    }

    /**
     * Check if the conversion matrix is complete (all conversions between base units are known).
     *
     * @return bool True if complete, false otherwise.
     */
    public function isMatrixComplete(): bool
    {
        $baseUnits = array_keys($this->units);
        foreach ($baseUnits as $initUnit) {
            foreach ($baseUnits as $finUnit) {
                if (!isset($this->conversions[$initUnit][$finUnit])) {
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
     */
    public function printMatrix()
    {
        $colWidth = 20;
        $baseUnits = array_keys($this->units);

        echo '+------+';
        foreach ($baseUnits as $baseUnit) {
            echo str_repeat('-', $colWidth) . '+';
        }
        echo "\n";

        echo '|      |';
        foreach ($baseUnits as $baseUnit) {
            echo str_pad($baseUnit, $colWidth, ' ', STR_PAD_BOTH) . '|';
        }
        echo "\n";

        echo '+------+';
        foreach ($baseUnits as $baseUnit) {
            echo str_repeat('-', $colWidth) . '+';
        }
        echo "\n";

        foreach ($baseUnits as $initUnit) {
            echo '|' . str_pad($initUnit, 6) . '|';
            foreach ($baseUnits as $finUnit) {
                if (isset($this->conversions[$initUnit][$finUnit])) {
                    $mult = $this->conversions[$initUnit][$finUnit]->multiplier->value;
                    $strMult = sprintf('%.10g', $mult);
                    echo str_pad($strMult, $colWidth);
                } else {
                    echo str_pad('?', $colWidth);
                }
                echo '|';
            }
            echo "\n";
        }

        echo '+------+';
        foreach ($baseUnits as $baseUnit) {
            echo str_repeat('-', $colWidth) . '+';
        }
        echo "\n";
    }

    /**
     * Dump the conversion matrix contents for debugging purposes.
     *
     * @return void
     */
    public function dumpMatrix()
    {
        echo "\n";
        echo "CONVERSION MATRIX\n";
        foreach ($this->conversions as $initBase => $conversions) {
            foreach ($conversions as $finBase => $conversion) {
                echo "$conversion\n";
            }
        }
        echo "\n";
        echo "\n";
    }

    // endregion
}
