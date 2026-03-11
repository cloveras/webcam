<?php
/**
 * SunCalculator class
 * 
 * Handles all sun time calculations including sunrise, sunset, dawn, dusk,
 * midnight sun, and polar night periods.
 */
class SunCalculator {
    
    private float $latitude;
    private float $longitude;
    public function __construct(float $latitude, float $longitude) {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }
    
    /**
     * Check if the given timestamp falls within the midnight sun period
     */
    public function isMidnightSun(int $timestamp): bool {
        $month = (int)date('m', $timestamp);
        $day = (int)date('d', $timestamp);
        
        $start = WebcamConfig::MIDNIGHT_SUN_PERIOD['start'];
        $end = WebcamConfig::MIDNIGHT_SUN_PERIOD['end'];
        
        return ($month == $start['month'] && $day >= $start['day']) || 
               ($month == 6) || 
               ($month == $end['month'] && $day <= $end['day']);
    }
    
    /**
     * Check if the given timestamp falls within the polar night period
     */
    public function isPolarNight(int $timestamp): bool {
        $month = (int)date('m', $timestamp);
        $day = (int)date('d', $timestamp);
        
        $start = WebcamConfig::POLAR_NIGHT_PERIOD['start'];
        $end = WebcamConfig::POLAR_NIGHT_PERIOD['end'];
        
        return ($month == $start['month'] && $day >= $start['day']) || 
               ($month == $end['month'] && $day <= $end['day']);
    }
    
    /**
     * Find sunrise, sunset, dawn, and dusk times for a given timestamp
     * 
     * Returns array with:
     * - sunrise: Unix timestamp
     * - sunset: Unix timestamp
     * - dawn: Unix timestamp
     * - dusk: Unix timestamp
     * - midnight_sun: boolean
     * - polar_night: boolean
     * 
     * Handles midnight sun and polar night by faking appropriate times.
     * Adjusts dawn and dusk to be on the same day as sunrise and sunset.
     */
    public function findSunTimes(int $timestamp): array {
        
        $year = (int)date('Y', $timestamp);
        $month = (int)date('m', $timestamp);
        $day = (int)date('d', $timestamp);
        
        $midnight_sun = $this->isMidnightSun($timestamp);
        $polar_night = $this->isPolarNight($timestamp);
        
        if ($midnight_sun) {
            return $this->getMidnightSunTimes($year, $month, $day, $midnight_sun, $polar_night);
        } elseif ($polar_night) {
            return $this->getPolarNightTimes($year, $month, $day, $midnight_sun, $polar_night);
        } else {
            return $this->getNormalSunTimes($timestamp, $year, $month, $day, $midnight_sun, $polar_night);
        }
    }
    
    /**
     * Get fake sun times for midnight sun period
     */
    private function getMidnightSunTimes(int $year, int $month, int $day, bool $midnight_sun, bool $polar_night): array {
        
        // Fake sunrise and sunset to show some images
        $sunrise = mktime(0, 0, 1, $month, $day, $year);    // 00:00:01
        $sunset  = mktime(23, 59, 59, $month, $day, $year); // 23:59:59
        $dawn    = $sunrise;
        $dusk    = $sunset;
        
        return [$sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night];
    }
    
    /**
     * Get fake sun times for polar night period
     */
    private function getPolarNightTimes(int $year, int $month, int $day, bool $midnight_sun, bool $polar_night): array {
        
        $adjust_hours = WebcamConfig::POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS;
        
        // Fake sunrise and sunset to show some images
        $sunrise = mktime(WebcamConfig::POLAR_NIGHT_FAKE_SUNRISE_HOUR, 0, 0, $month, $day, $year);
        $sunset  = mktime(WebcamConfig::POLAR_NIGHT_FAKE_SUNSET_HOUR, 0, 0, $month, $day, $year);
        $dawn    = mktime(WebcamConfig::POLAR_NIGHT_FAKE_SUNRISE_HOUR - $adjust_hours, 0, 0, $month, $day, $year);
        $dusk    = mktime(WebcamConfig::POLAR_NIGHT_FAKE_SUNSET_HOUR + $adjust_hours, 0, 0, $month, $day, $year);
        
        return [$sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night];
    }
    
    /**
     * Get actual calculated sun times for normal days
     */
    private function getNormalSunTimes(int $timestamp, int $year, int $month, int $day, bool $midnight_sun, bool $polar_night): array {
        
        // Get sun info using PHP's built-in functionality
        $sun_info = date_sun_info($timestamp, $this->latitude, $this->longitude);
        
        $sunrise = $sun_info['sunrise'];
        $sunset = $sun_info['sunset'];
        
        // Fix sunrise if invalid. PHP's date_sun_info() returns 1 or a specific bogus timestamp
        // (1715464868 = 2024-05-12 00:01:08 UTC) when it cannot calculate a value (e.g. polar night).
        if ($sunrise == 1 || $sunrise == 1715464868 || !$sunrise) {
            $sunrise = mktime(0, 0, 0, $month, $day, $year);
        }

        // Fix sunset if invalid (same bogus values as sunrise above).
        if ($sunset == 1 || $sunset == 1715464868 || !$sunset) {
            $sunset = mktime(23, 59, 59, $month, $day, $year);
        }

        $dawn = $this->calculateDawn($sun_info, $sunrise, $month, $day, $year);
        $dusk = $this->calculateDusk($sun_info, $sunset, $month, $day, $year);

        return [$sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night];
    }
    
    /**
     * Calculate dawn, ensuring it's on the same day as sunrise
     */
    private function calculateDawn(array $sun_info, int $sunrise, int $month, int $day, int $year): int {
        $adjust_seconds = WebcamConfig::POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS * 60 * 60;
        
        $dawn = $sun_info['nautical_twilight_begin'];
        
        if ($dawn == 1715464868 || !$dawn) {
                $dawn = $sunrise - $adjust_seconds;
            
            // If dawn is on the previous day, set it to midnight
            if (date('d', $dawn) != $day) {
                        $dawn = mktime(0, 0, 0, $month, $day, $year);
            }
            }
        
        return $dawn;
    }
    
    /**
     * Calculate dusk, ensuring it's on the same day as sunset
     */
    private function calculateDusk(array $sun_info, int $sunset, int $month, int $day, int $year): int {
        $adjust_seconds = WebcamConfig::POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS * 60 * 60;
        
        $dusk = $sun_info['nautical_twilight_end'];
        
        if ($dusk == 1715464868 || !$dusk) {
                $dusk = $sunset + $adjust_seconds;
            
            // If dusk is on the next day, set it to end of day
            if (date('d', $dusk) != $day) {
                        $dusk = mktime(23, 59, 59, $month, $day, $year);
            }
            }
        
        return $dusk;
    }
    
}
