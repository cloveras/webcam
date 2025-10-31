<?php
/**
 * NavigationHelper class
 * 
 * Handles all navigation-related logic including finding previous/next items,
 * generating navigation URLs, and calculating month/year transitions.
 */
class NavigationHelper {
    
    /**
     * Find previous and next month, handling year boundaries
     * 
     * @param string|int $year
     * @param string|int $month
     * @return array [year_previous, month_previous, year_next, month_next]
     */
    public function findPreviousAndNextMonth($year, $month): array {
        $year = (int)$year;
        $month = (int)$month;
        
        $month_previous = ($month == 1) ? 12 : $month - 1;
        $year_previous = ($month == 1) ? $year - 1 : $year;
        
        $month_next = ($month == 12) ? 1 : $month + 1;
        $year_next = ($month == 12) ? $year + 1 : $year;
        
        // Format as zero-padded strings
        $month_previous = sprintf("%02d", $month_previous);
        $month_next = sprintf("%02d", $month_next);
        $year_previous = sprintf("%04d", $year_previous);
        $year_next = sprintf("%04d", $year_next);
        
        return [$year_previous, $month_previous, $year_next, $month_next];
    }
    
    /**
     * Generate navigation URL with query parameters
     * 
     * @param string $type Type of view (day, month, year, one, last)
     * @param array $params Additional parameters
     * @return string URL with query string
     */
    public function buildUrl(string $type, array $params = []): string {
        $params['type'] = $type;
        return '?' . http_build_query($params);
    }
}
