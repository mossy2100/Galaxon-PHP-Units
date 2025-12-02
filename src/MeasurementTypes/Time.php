<?php

declare(strict_types=1);

namespace Galaxon\Units\MeasurementTypes;

use DateInterval;
use Galaxon\Core\Floats;
use Galaxon\Core\Numbers;
use Galaxon\Units\Measurement;
use Override;
use ValueError;

class Time extends Measurement
{
    // region Constants

    /**
     * Constants for specifying the smallest unit in time part conversions.
     */
    public const int UNIT_YEAR = 0;
    public const int UNIT_MONTH = 1;
    public const int UNIT_WEEK = 2;
    public const int UNIT_DAY = 3;
    public const int UNIT_HOUR = 4;
    public const int UNIT_MINUTE = 5;
    public const int UNIT_SECOND = 6;

    // endregion

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

        // Seconds.
        $seconds += $interval->s;

        // Microseconds (f is a float from 0-1 representing fraction of a second).
        if ($interval->f > 0) {
            $seconds += $interval->f;
        }

        // Handle negative intervals.
        if ($interval->invert === 1) {
            $seconds = -$seconds;
        }

        return new self($seconds, 's');
    }

    // endregion

    // region Instance methods

    /**
     * Convert time to component parts.
     *
     * Returns an array with components from years down to the smallest unit.
     * Only the last component may have a fractional part; others are whole numbers.
     *
     * Uses naive conversion, which assumes years and months have a constant, average length:
     * - 1 year = 365.2425 days
     * - 1 month = 30.436875 days
     * - 1 week = 7 days
     *
     * @param int $smallestUnit The smallest unit to include (UNIT_YEAR through UNIT_SECOND).
     * @return float[] Array of time components.
     * @throws ValueError If $smallestUnit is not valid.
     */
    public function toParts(int $smallestUnit = self::UNIT_SECOND): array
    {
        if ($smallestUnit < self::UNIT_YEAR || $smallestUnit > self::UNIT_SECOND) {
            throw new ValueError('Invalid smallest unit specified.');
        }

        $converter = static::getUnitConverter();
        $timeInSeconds = $this->to('s');
        $sign = Numbers::sign($timeInSeconds->value, false);
        $remaining = abs($timeInSeconds->value);

        $parts = [];

        // Extract years.
        if ($smallestUnit >= self::UNIT_YEAR) {
            $secondsPerYear = $converter->convert(1, 'y', 's');
            $years = floor($remaining / $secondsPerYear);
            $remaining -= $years * $secondsPerYear;

            if ($smallestUnit === self::UNIT_YEAR) {
                $years += $remaining / $secondsPerYear;
                $parts[] = Floats::normalizeZero($years * $sign);
                return $parts;
            }

            $parts[] = Floats::normalizeZero($years * $sign);
        }

        // Extract months.
        if ($smallestUnit >= self::UNIT_MONTH) {
            $secondsPerMonth = $converter->convert(1, 'mo', 's');
            $months = floor($remaining / $secondsPerMonth);
            $remaining -= $months * $secondsPerMonth;

            if ($smallestUnit === self::UNIT_MONTH) {
                $months += $remaining / $secondsPerMonth;
                $parts[] = Floats::normalizeZero($months * $sign);
                return $parts;
            }

            $parts[] = Floats::normalizeZero($months * $sign);
        }

        // Extract weeks.
        if ($smallestUnit >= self::UNIT_WEEK) {
            $secondsPerWeek = $converter->convert(1, 'w', 's');
            $weeks = floor($remaining / $secondsPerWeek);
            $remaining -= $weeks * $secondsPerWeek;

            if ($smallestUnit === self::UNIT_WEEK) {
                $weeks += $remaining / $secondsPerWeek;
                $parts[] = Floats::normalizeZero($weeks * $sign);
                return $parts;
            }

            $parts[] = Floats::normalizeZero($weeks * $sign);
        }

        // Extract days.
        if ($smallestUnit >= self::UNIT_DAY) {
            $secondsPerDay = $converter->convert(1, 'd', 's');
            $days = floor($remaining / $secondsPerDay);
            $remaining -= $days * $secondsPerDay;

            if ($smallestUnit === self::UNIT_DAY) {
                $days += $remaining / $secondsPerDay;
                $parts[] = Floats::normalizeZero($days * $sign);
                return $parts;
            }

            $parts[] = Floats::normalizeZero($days * $sign);
        }

        // Extract hours.
        if ($smallestUnit >= self::UNIT_HOUR) {
            $secondsPerHour = $converter->convert(1, 'h', 's');
            $hours = floor($remaining / $secondsPerHour);
            $remaining -= $hours * $secondsPerHour;

            if ($smallestUnit === self::UNIT_HOUR) {
                $hours += $remaining / $secondsPerHour;
                $parts[] = Floats::normalizeZero($hours * $sign);
                return $parts;
            }

            $parts[] = Floats::normalizeZero($hours * $sign);
        }

        // Extract minutes.
        if ($smallestUnit >= self::UNIT_MINUTE) {
            $secondsPerMinute = $converter->convert(1, 'min', 's');
            $minutes = floor($remaining / $secondsPerMinute);
            $remaining -= $minutes * $secondsPerMinute;

            if ($smallestUnit === self::UNIT_MINUTE) {
                $minutes += $remaining / $secondsPerMinute;
                $parts[] = Floats::normalizeZero($minutes * $sign);
                return $parts;
            }

            $parts[] = Floats::normalizeZero($minutes * $sign);
        }

        // Remaining seconds.
        $parts[] = Floats::normalizeZero($remaining * $sign);

        return $parts;
    }

    /**
     * Convert time to a DateInterval specification string.
     *
     * Format: P[y]Y[m]M[w]W[d]DT[h]H[i]M[s]S
     *
     * @param int $smallestUnit The smallest unit to include.
     * @return string A DateInterval specification string.
     */
    public function toDateIntervalSpecifier(int $smallestUnit = self::UNIT_SECOND): string
    {
        $parts = $this->abs()->toParts($smallestUnit);
        $sign = $this->value < 0 ? '-' : '';

        $spec = 'P';
        $labels = ['Y', 'M', 'W', 'D', 'H', 'M', 'S'];
        $timeSeparatorAdded = false;

        // Build the specification string.
        for ($i = 0; $i <= $smallestUnit; $i++) {
            $value = $parts[$i] ?? 0;

            // Add time separator before hours.
            if ($i === self::UNIT_HOUR && !$timeSeparatorAdded) {
                $spec .= 'T';
                $timeSeparatorAdded = true;
            }

            if ($value != 0) {
                // Use floor for all but the last component.
                if ($i < $smallestUnit) {
                    $spec .= floor(abs($value)) . $labels[$i];
                } else {
                    $spec .= abs($value) . $labels[$i];
                }
            }
        }

        // If nothing was added, return P0D.
        if ($spec === 'P' || $spec === 'PT') {
            return 'P0D';
        }

        return $sign . $spec;
    }

    /**
     * Convert time to a PHP DateInterval object.
     *
     * @param int $smallestUnit The smallest unit to include.
     * @return DateInterval A new DateInterval object.
     */
    public function toDateInterval(int $smallestUnit = self::UNIT_SECOND): DateInterval
    {
        $spec = $this->toDateIntervalSpecifier($smallestUnit);
        return new DateInterval($spec);
    }

    /**
     * Format time as component parts with units.
     *
     * Returns a string like "1y 3mo 2w 4d 12h 34min 56.789s".
     * For units other than seconds, the smallest unit is always shown as an integer (precision 0).
     *
     * @param int $smallestUnit The smallest unit to include.
     * @param int $precision Number of decimal places for seconds (ignored if smallestUnit is not UNIT_SECOND).
     * @return string Formatted time string.
     */
    public function formatParts(int $smallestUnit = self::UNIT_SECOND, int $precision = 0): string
    {
        $sign = $this->value < 0 ? '-' : '';
        $parts = $this->abs()->toParts($smallestUnit);
        $labels = ['y', 'mo', 'w', 'd', 'h', 'min', 's'];

        $result = [];

        for ($i = 0; $i <= $smallestUnit; $i++) {
            $value = $parts[$i] ?? 0;

            // Skip zero components except for the last one.
            if ($value == 0 && $i < $smallestUnit) {
                continue;
            }

            // Format the value.
            if ($i === self::UNIT_SECOND) {
                $formatted = self::formatValue($value, 'f', $precision);
            } else {
                $formatted = (string)(int)$value;
            }

            $result[] = $formatted . $labels[$i];
        }

        // If no components, return "0" with the smallest unit.
        if (empty($result)) {
            return $sign . '0' . $labels[$smallestUnit];
        }

        return $sign . implode(' ', $result);
    }

    // endregion

    // region Measurement methods

    /**
     * Get the units for Time measurements.
     *
     * @return array<string, bool> Array of units with boolean indicating if they accept prefixes.
     */
    #[Override]
    public static function getBaseUnits(): array
    {
        return [
            's' => true,    // second (accepts metric prefixes)
            'min' => false, // minute
            'h' => false,   // hour
            'd' => false,   // day
            'w' => false,   // week
            'mo' => false,  // month
            'y' => false,   // year
            'c' => false,   // century
        ];
    }

    /**
     * Get the conversions for Time measurements.
     *
     * @return array<array<string, string, int|float>>
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
}
