<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use DateInterval;
use Galaxon\Units\Measurement;
use Override;
use TypeError;
use ValueError;

class Time extends Measurement
{
    // region Factory methods

    /**
     * Create a Time from a PHP DateInterval object.
     *
     * Uses naive conversion based on average values:
     * - 1 year = 365.2425 days
     * - 1 month = 30.436875 days (365.2425 / 12)
     * - 1 week = 7 days
     *
     * @param DateInterval $interval The DateInterval to convert.
     * @return self A new Time instance.
     */
    public static function fromDateInterval(DateInterval $interval): self
    {
        // Convert all components to seconds using our conversion system.
        $converter = static::getUnitConverter();

        $seconds = 0.0;

        // Years to seconds.
        if ($interval->y > 0) {
            $seconds += $converter->convert($interval->y, 'y', 's');
        }

        // Months to seconds.
        if ($interval->m > 0) {
            $seconds += $converter->convert($interval->m, 'mo', 's');
        }

        // Days to seconds (DateInterval stores total days, not weeks).
        if ($interval->d > 0) {
            $seconds += $converter->convert($interval->d, 'd', 's');
        }

        // Hours to seconds.
        if ($interval->h > 0) {
            $seconds += $converter->convert($interval->h, 'h', 's');
        }

        // Minutes to seconds.
        if ($interval->i > 0) {
            $seconds += $converter->convert($interval->i, 'min', 's');
        }

        // Seconds and microseconds (f is a float from 0-1 representing fraction of a second).
        $seconds += $interval->s + $interval->f;

        // Handle negative intervals.
        if ($interval->invert === 1) {
            $seconds = -$seconds;
        }

        return new self($seconds, 's');
    }

    // endregion

    // region Extraction methods

    /**
     * Get the units for Time measurements.
     *
     * @return array<string, int> Array of units with allowed prefixes flags.
     */
    #[Override]
    public static function getUnits(): array
    {
        return [
            's'   => self::PREFIX_CODE_METRIC,  // second
            'min' => 0,  // minute
            'h'   => 0,  // hour
            'd'   => 0,  // day
            'w'   => 0,  // week
            'mo'  => 0,  // month
            'y'   => 0,  // year
            'c'   => 0,  // century
        ];
    }

    /**
     * Get the conversions for Time measurements.
     *
     * These conversion factors are basic. Leap seconds are not considered, and the year-to-day conversion is based on
     * the average length of a year in the Gregorian calendar. If you want, you can add or update conversions using the
     * `Time::getUnitConverter()->addConversion()` method.
     *
     * @return array<array{0: string, 1: string, 2: float, 3?: float}> Array of conversion definitions.
     */
    #[Override]
    public static function getConversions(): array
    {
        return [
            ['min', 's', 60],
            ['h', 'min', 60],
            ['d', 'h', 24],
            ['w', 'd', 7],
            ['y', 'mo', 12],
            ['y', 'd', 365.2425],
            ['c', 'y', 100]
        ];
    }

    // endregion

    // region Conversion methods

    /**
     * Convert time to a DateInterval specification string.
     *
     * Format: P[y]Y[m]M[w]W[d]DT[h]H[i]M[s]S
     *
     * @param string $smallestUnit The smallest unit to include (default 's').
     * @return string A DateInterval specification string.
     * @throws ValueError If the smallest unit argument is invalid.
     */
    public function toDateIntervalSpecifier(string $smallestUnit = 's'): string
    {
        // Validate argument.
        self::validateSmallestUnit($smallestUnit);
        self::validateAndTransformPartUnits();

        // Prep.
        $partUnits = static::getPartUnits();
        $smallestUnitIndex = (int)array_search($smallestUnit, $partUnits, true);
        $parts = $this->toPartsArray($smallestUnit, 0);  // DateInterval requires integer parts.
        $spec = 'P';
        $labels = ['Y', 'M', 'W', 'D', 'H', 'M', 'S'];
        $timeSeparatorAdded = false;

        // Build the specification string.
        for ($i = 0; $i <= $smallestUnitIndex; $i++) {
            $unit = $partUnits[$i];
            $value = $parts[$unit] ?? 0;

            // Add time separator before hours.
            if ($unit === 'h' && !$timeSeparatorAdded) {
                $spec .= 'T';
                $timeSeparatorAdded = true;
            }

            // Add the specifier part if it isn't 0.
            if ($parts[$unit] !== 0) {
                $spec .= $value . $labels[$i];
            }
        }

        // If nothing was added, return P0D.
        if ($spec === 'P' || $spec === 'PT') {
            return 'P0D';
        }

        return $spec;
    }

    /**
     * Convert time to a PHP DateInterval object.
     *
     * @param string $smallestUnit The smallest unit to include (default 's').
     * @return DateInterval A new DateInterval object.
     * @throws ValueError If the smallest unit argument is invalid.
     */
    public function toDateInterval(string $smallestUnit = 's'): DateInterval
    {
        // Validate argument.
        self::validateSmallestUnit($smallestUnit);

        // Get the specifier string.
        $spec = $this->toDateIntervalSpecifier($smallestUnit);

        // Construct the DateInterval.
        $dateInterval = new DateInterval($spec);

        // If the time value is negative, invert the DateInterval.
        if ($this->value < 0) {
            $dateInterval->invert = 1;
        }

        return $dateInterval;
    }

    // endregion

    // region Part-related methods

    /**
     * Ordered list of Time unit abbreviations from largest (years) to smallest (seconds).
     * Used for parts decomposition and validation.
     *
     * @return array<int|string, string>
     */
    #[Override]
    public static function getPartUnits(): array
    {
        return ['y', 'mo', 'w', 'd', 'h', 'min', 's'];
    }

    /**
     * Create a Time as a sum of times in different units.
     *
     * All parts must be non-negative.
     * If the Time is negative, set the $sign parameter to -1.
     *
     * NB: This method doesn't include a parameter for weeks, as this could be confusing and lead to bugs.
     * Many date and time constructors don't include a parameter for weeks, and only have the 6 usual ones.
     * So, this design is following the "Principle of Least Surprise".
     * If you need to create a Time from weeks, you can convert weeks to days, or use fromPartsArray() instead, which
     * accepts a 'w' key.
     *
     * @param float $years The number of years.
     * @param float $months The number of months.
     * @param float $days The number of days.
     * @param float $hours The number of hours.
     * @param float $minutes The number of minutes.
     * @param float $seconds The number of seconds.
     * @param int $sign -1 if the Time is negative, 1 (or omitted) otherwise.
     * @return static A new Time in seconds with a magnitude equal to the sum of the parts.
     * @throws TypeError If any of the values are not numbers.
     * @throws ValueError If any of the values are non-finite or negative.
     */
    public static function fromParts(
        float $years = 0,
        float $months = 0,
        float $days = 0,
        float $hours = 0,
        float $minutes = 0,
        float $seconds = 0,
        int $sign = 1
    ): static {
        return self::fromPartsArray([
            'y'   => $years,
            'mo'  => $months,
            'd'   => $days,
            'h'   => $hours,
            'min' => $minutes,
            's'   => $seconds,
            'sign' => $sign
        ]);
    }

    /**
     * Format time as component parts with units.
     *
     * Returns a string like "1y 3mo 2w 4d 12h 34min 56.789s".
     * Units other than the smallest unit are shown as integers.
     *
     * @param string $smallestUnit The smallest unit to include (default 's').
     * @param ?int $precision The number of decimal places for rounding the smallest unit, or null for no rounding.
     * @param bool $showZeros If true, show all components including zeros (default false for Time).
     * @return string Formatted time string.
     * @throws ValueError If any arguments are invalid.
     */
    #[Override]
    public function formatParts(string $smallestUnit = 's', ?int $precision = null, bool $showZeros = false): string
    {
        return parent::formatParts($smallestUnit, $precision, $showZeros);
    }

    // endregion
}
