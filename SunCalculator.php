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
    private bool $debug;
    
    public function __construct(float $latitude, float $longitude, bool $debug = false) {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->debug = $debug;
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
        $this->debugLog("findSunTimes($timestamp) (" . date('Y-m-d H:i', $timestamp) . ")");
        
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
        $this->debugLog("MIDNIGHT SUN!");
        
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
        $this->debugLog("POLAR NIGHT!");
        
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
        $this->debugLog("NOT MIDNIGHT SUN OR POLAR NIGHT! timestamp: $timestamp, date: " . date('Y-m-d H:i:s', $timestamp));
        
        // Get sun info using PHP's built-in functionality
        $sun_info = date_sun_info($timestamp, $this->latitude, $this->longitude);
        
        if ($this->debug) {
            $this->debugLog("date_sun_info($timestamp, {$this->latitude}, {$this->longitude})");
            foreach ($sun_info as $key => $val) {
                $this->debugLog("$key: [$val] " . date("Y-m-d H:i", $val));
            }
        }
        
        $sunrise = $sun_info['sunrise'];
        $sunset = $sun_info['sunset'];
        
        // Fix sunrise if invalid
        if ($sunrise == 1 || $sunrise == 1715464868 || !$sunrise) {
            $this->debugLog("Sunrise to fix: " . date('Y-m-d H:i', $sunrise));
            $sunrise = mktime(0, 0, 0, $month, $day, $year);
            $this->debugLog("Sunrise fixed: " . date('Y-m-d H:i', $sunrise));
        }
        
        // Fix sunset if invalid
        if ($sunset == 1 || $sunset == 1715464868 || !$sunset) {
            $this->debugLog("Sunset to fix: " . date('Y-m-d H:i', $sunset));
            $sunset = mktime(23, 59, 59, $month, $day, $year);
            $this->debugLog("Sunset fixed: " . date('Y-m-d H:i', $sunset));
        }
        
        $dawn = $this->calculateDawn($sun_info, $sunrise, $month, $day, $year);
        $dusk = $this->calculateDusk($sun_info, $sunset, $month, $day, $year);
        
        $this->debugLog("Final times - dawn: " . date('Y-m-d H:i', $dawn) . 
                       ", sunrise: " . date('Y-m-d H:i', $sunrise) . 
                       ", sunset: " . date('Y-m-d H:i', $sunset) . 
                       ", dusk: " . date('Y-m-d H:i', $dusk));
        
        return [$sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night];
    }
    
    /**
     * Calculate dawn, ensuring it's on the same day as sunrise
     */
    private function calculateDawn(array $sun_info, int $sunrise, int $month, int $day, int $year): int {
        $adjust_seconds = WebcamConfig::POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS * 60 * 60;
        
        $dawn = $sun_info['nautical_twilight_begin'];
        $this->debugLog("Dawn initial: $dawn (" . date('Y-m-d H:i', $dawn) . ")");
        
        if ($dawn == 1715464868 || !$dawn) {
            $this->debugLog("No nautical_twilight_begin, setting dawn to: sunrise - $adjust_seconds seconds");
            $dawn = $sunrise - $adjust_seconds;
            
            // If dawn is on the previous day, set it to midnight
            if (date('d', $dawn) != $day) {
                $this->debugLog("Dawn on previous day, setting to 00:00:00");
                $dawn = mktime(0, 0, 0, $month, $day, $year);
            }
            $this->debugLog("Dawn adjusted: $dawn (" . date('Y-m-d H:i', $dawn) . ")");
        }
        
        return $dawn;
    }
    
    /**
     * Calculate dusk, ensuring it's on the same day as sunset
     */
    private function calculateDusk(array $sun_info, int $sunset, int $month, int $day, int $year): int {
        $adjust_seconds = WebcamConfig::POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS * 60 * 60;
        
        $dusk = $sun_info['nautical_twilight_end'];
        $this->debugLog("Dusk initial: $dusk (" . date('Y-m-d H:i', $dusk) . ")");
        
        if ($dusk == 1715464868 || !$dusk) {
            $this->debugLog("No nautical_twilight_end, setting dusk to: sunset + $adjust_seconds seconds");
            $dusk = $sunset + $adjust_seconds;
            
            // If dusk is on the next day, set it to end of day
            if (date('d', $dusk) != $day) {
                $this->debugLog("Dusk on next day, setting to 23:59:59");
                $dusk = mktime(23, 59, 59, $month, $day, $year);
            }
            $this->debugLog("Dusk adjusted: $dusk (" . date('Y-m-d H:i', $dusk) . ")");
        }
        
        return $dusk;
    }
    
    /**
     * Debug logging helper
     */
    private function debugLog(string $message): void {
        if ($this->debug) {
            echo "$message<br/>\n";
        }
    }
}
