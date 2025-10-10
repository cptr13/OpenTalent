<?php
// includes/date_helpers.php
declare(strict_types=1);

/**
 * Add business days to a given date.
 * Skips weekends (Sat/Sun) and provided holidays (Y-m-d strings).
 */
function addBusinessDays(DateTimeImmutable $start, int $days, array $holidays = []): DateTimeImmutable {
    $holidays = array_map(fn($d) => (new DateTimeImmutable($d))->format('Y-m-d'), $holidays);
    $remaining = $days;
    $date = $start;

    while ($remaining > 0) {
        $date = $date->modify('+1 day');
        $dow = (int)$date->format('N'); // 1=Mon .. 7=Sun
        if ($dow <= 5 && !in_array($date->format('Y-m-d'), $holidays, true)) {
            $remaining--;
        }
    }
    return $date;
}

/**
 * Generate standard U.S. business holidays (for the given year).
 * Excludes MLK, Presidents Day, Juneteenth, Columbus Day, and Veterans Day.
 * Adjusts observed dates when holidays fall on weekends.
 */
function generateCommonUSHolidays(int $year): array {
    $holidays = [];

    // Helper: if holiday lands on weekend, shift observed day
    $adjust = function (string $ymd): string {
        $d = new DateTimeImmutable($ymd);
        $dow = (int)$d->format('N');
        if ($dow === 6) { // Saturday → Friday
            $d = $d->modify('-1 day');
        } elseif ($dow === 7) { // Sunday → Monday
            $d = $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    };

    // Fixed-date holidays
    $holidays[] = $adjust(sprintf('%04d-01-01', $year)); // New Year's Day
    $holidays[] = $adjust(sprintf('%04d-07-04', $year)); // Independence Day
    $holidays[] = $adjust(sprintf('%04d-12-25', $year)); // Christmas Day

    // Memorial Day = last Monday in May
    $lastDayMay = new DateTimeImmutable("$year-05-31");
    while ($lastDayMay->format('N') != 1) { // 1=Monday
        $lastDayMay = $lastDayMay->modify('-1 day');
    }
    $holidays[] = $lastDayMay->format('Y-m-d');

    // Labor Day = first Monday in September
    $firstSept = new DateTimeImmutable("$year-09-01");
    while ($firstSept->format('N') != 1) {
        $firstSept = $firstSept->modify('+1 day');
    }
    $holidays[] = $firstSept->format('Y-m-d');

    // Thanksgiving = fourth Thursday in November
    $thanksgiving = new DateTimeImmutable("fourth thursday of November $year");
    $holidays[] = $thanksgiving->format('Y-m-d');

    // Day after Thanksgiving (optional, common business holiday)
    $dayAfter = $thanksgiving->modify('+1 day');
    $holidays[] = $dayAfter->format('Y-m-d');

    return $holidays;
}
