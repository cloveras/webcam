<?php
/**
 * ImageFileManager class
 * 
 * Handles all file system operations related to webcam images:
 * - Finding images in directories
 * - Extracting date/time from filenames
 * - File operations like renaming
 */
class ImageFileManager {
    
    private bool $debug;
    
    public function __construct(bool $debug = false) {
        $this->debug = $debug;
    }
    
    /**
     * Extract date/time components from image filename
     * 
     * @param string $filename Example: "20231114134047.jpg" or "2023/11/14/20231114134047.jpg"
     * @return array [year, month, day, hour, minute, seconds]
     */
    public function splitImageFilename(string $filename): array {
        $yyyymmddhhmmss = $this->getYYYYMMDDHHMMSS($filename);
        
        $year = substr($yyyymmddhhmmss, 0, 4);
        $month = substr($yyyymmddhhmmss, 4, 2);
        $day = substr($yyyymmddhhmmss, 6, 2);
        $hour = substr($yyyymmddhhmmss, 8, 2);
        $minute = substr($yyyymmddhhmmss, 10, 2);
        $seconds = substr($yyyymmddhhmmss, 12, 2);
        
        $this->debugLog("splitImageFilename($filename): $year-$month-$day $hour:$minute:$seconds");
        
        return [$year, $month, $day, $hour, $minute, $seconds];
    }
    
    /**
     * Extract only the date part (YYYYMMDDHHMMSS) from a filename
     * Removes directory path and .jpg extension
     * 
     * @param string $fullPath Example: "2023/11/14/20231114144049.jpg"
     * @return string Example: "20231114144049"
     */
    public function getYYYYMMDDHHMMSS(string $fullPath): string {
        return preg_replace("/[^0-9]/", "", pathinfo(basename($fullPath), PATHINFO_FILENAME));
    }
    
    /**
     * Find the latest image in today's directory
     * 
     * @return string Date part of filename (YYYYMMDDHHMMSS)
     */
    public function findLatestImage(): string {
        [$year, $month, $day] = explode('-', date('Y-m-d'));
        
        if (is_dir("$year/$month/$day")) {
            $this->debugLog("NORMAL: Finding latest in $year/$month/$day/");
            $latest_image = max(glob("$year/$month/$day/*.jpg", GLOB_BRACE));
        } elseif (is_dir("$year/$month")) {
            $this->debugLog("MONTH: Finding latest in $year/$month/");
            $latest_image = max(glob("$year/$month/**/*.jpg", GLOB_BRACE));
        } elseif (is_dir("$year")) {
            $this->debugLog("YEAR: Finding latest in $year/");
            $latest_image = max(glob("$year/**/*.jpg", GLOB_BRACE));
        } else {
            return '';
        }
        
        $image = $this->getYYYYMMDDHHMMSS($latest_image);
        $this->debugLog("FOUND latest image: $image");
        
        return $image;
    }
    
    /**
     * Find the first day with images for a specific year and month
     * 
     * @return string Date in YYYYMMDD format (e.g., "20231101")
     */
    public function findFirstDayWithImages(string $year, string $month): string {
        $this->debugLog("findFirstDayWithImages($year, $month)");
        
        // Pattern matches directories like "20231101", "20231102", etc.
        $pattern = sprintf("%s%s*", $year, $month);
        $directories = glob($pattern);
        
        if (empty($directories)) {
            return '';
        }
        
        $directory = $directories[0];  // First one in that month
        $this->debugLog("First day with images: $directory");
        
        return $directory;
    }
    
    /**
     * Get all images in a directory for a specific day
     * 
     * @param string $directory Example: "2023/11/14"
     * @return array Array of image file paths
     */
    public function getAllImagesInDirectory(string $directory): array {
        $images = glob("$directory/*.jpg");
        $this->debugLog("getAllImagesInDirectory($directory): " . count($images) . " images found");
        
        return $images;
    }
    
    /**
     * Get the latest image in a directory for a specific hour
     * 
     * @param string $directory Example: "2023/11/14"
     * @param int $hour Hour (0-23)
     * @return string Full path to image or empty string
     */
    public function getLatestImageInDirectoryByDateHour(string $directory, int $hour): string {
        $date = preg_replace("/[^0-9]/", "", $directory);
        $hour_padded = sprintf("%02d", $hour);
        $images = glob("$directory/$date$hour_padded*.jpg");
        
        $this->debugLog("getLatestImageInDirectoryByDateHour($directory, $hour): Found " . 
                       count($images) . " images");
        
        return !empty($images) ? $images[0] : '';
    }
    
    /**
     * Find the first image after a given time
     * 
     * @param string $year
     * @param string $month
     * @param string $day
     * @param int $hour
     * @param int $minute
     * @param int $seconds
     * @return string Date part of filename (YYYYMMDDHHMMSS) or empty string
     */
    public function findFirstImageAfterTime(string $year, string $month, string $day, 
                                           int $hour, int $minute, int $seconds): string {
        $minute = sprintf("%02d", $minute);
        $seconds = sprintf("%02d", $seconds);
        $hour = sprintf("%02d", $hour);
        
        $this->debugLog("findFirstImageAfterTime($year, $month, $day, $hour, $minute, $seconds)");
        
        $imagePattern = sprintf("%s/%s/%s/%s%s%s%s*", 
                               $year, $month, $day, $year, $month, $day, $hour);
        
        $this->debugLog("Looking with pattern: $imagePattern");
        
        $images = glob($imagePattern);
        
        if (!empty($images)) {
            $image = $this->getYYYYMMDDHHMMSS($images[0]);
            $this->debugLog("Image found: $image");
            return $image;
        }
        
        $this->debugLog("No images found matching pattern: $imagePattern");
        return '';
    }
    
    /**
     * Check and rename files that haven't been processed by cron yet
     * This is a hack to handle files before cron processes them
     * 
     * @param string $filenamePrefix Prefix to search for and remove
     */
    public function checkAndRenameFilesHack(string $filenamePrefix): void {
        [$year, $month, $day] = explode('-', date('Y-m-d'));
        
        $this->debugLog("Checking for files to rename: $year/$month/$day/$filenamePrefix*");
        
        $images = glob("$year/$month/$day/$filenamePrefix*");
        $this->debugLog("Found " . count($images) . " images to rename");
        
        foreach ($images as $imageToRename) {
            $newName = str_replace($filenamePrefix, '', $imageToRename);
            $this->debugLog("rename($imageToRename, $newName)");
            rename($imageToRename, $newName);
        }
    }
    
    /**
     * Collect images to prefetch for performance optimization
     * 
     * @param string $type Type of prefetch: 'month', 'day', or 'single'
     * @param array $params Parameters specific to the type
     * @return array Array of image paths to prefetch
     */
    public function collectPrefetchImages(string $type, array $params): array {
        $prefetch_images = [];
        
        switch ($type) {
            case 'month':
                // Prefetch first 5 images of the month
                $year = $params['year'];
                $month = $params['month'];
                $monthly_hour = $params['monthly_hour'] ?? 12;
                $size = $params['size'] ?? 'mini';
                
                $directories = glob("$year/$month/*", GLOB_ONLYDIR);
                if ($directories) {
                    sort($directories);
                    $count = 0;
                    foreach ($directories as $directory) {
                        if ($count >= 5) break;
                        $image = $this->getLatestImageInDirectoryByDateHour($directory, $monthly_hour);
                        if ($image) {
                            $yyyymmddhhmmss = $this->getYYYYMMDDHHMMSS($image);
                            [$img_year, $img_month, $img_day] = $this->splitImageFilename($yyyymmddhhmmss);
                            
                            if ($size == "mini" || empty($size)) {
                                if (file_exists("$img_year/$img_month/$img_day/mini/$yyyymmddhhmmss.jpg")) {
                                    $prefetch_images[] = "$img_year/$img_month/$img_day/mini/$yyyymmddhhmmss.jpg";
                                } else {
                                    $prefetch_images[] = "$img_year/$img_month/$img_day/$yyyymmddhhmmss.jpg";
                                }
                            } else {
                                $prefetch_images[] = "$img_year/$img_month/$img_day/$yyyymmddhhmmss.jpg";
                            }
                            $count++;
                        }
                    }
                }
                break;
                
            case 'day':
                // Prefetch first 5 images of the day
                $directory = $params['directory'];
                $dawn = $params['dawn'];
                $dusk = $params['dusk'];
                $size = $params['size'] ?? 'mini';
                
                if (file_exists($directory)) {
                    $all_images = glob("$directory/*.jpg");
                    // Get last 10 images, then filter
                    $recent_images = array_slice($all_images, -10);
                    rsort($recent_images);
                    $count = 0;
                    
                    foreach ($recent_images as $img) {
                        if ($count >= 5) break;
                        $img_yyyymmddhhmmss = $this->getYYYYMMDDHHMMSS($img);
                        [$img_year, $img_month, $img_day, $img_hour, $img_minute, $img_seconds] = 
                            $this->splitImageFilename($img_yyyymmddhhmmss);
                        
                        $img_timestamp = mktime((int)$img_hour, (int)$img_minute, (int)$img_seconds,
                                               (int)$img_month, (int)$img_day, (int)$img_year);
                        
                        if ($img_timestamp >= $dawn && $img_timestamp <= $dusk) {
                            if ($size == "mini" || empty($size)) {
                                if (file_exists("$img_year/$img_month/$img_day/mini/$img_yyyymmddhhmmss.jpg")) {
                                    $prefetch_images[] = "$img_year/$img_month/$img_day/mini/$img_yyyymmddhhmmss.jpg";
                                } else {
                                    $prefetch_images[] = "$img_year/$img_month/$img_day/$img_yyyymmddhhmmss.jpg";
                                }
                            } else {
                                $prefetch_images[] = "$img_year/$img_month/$img_day/$img_yyyymmddhhmmss.jpg";
                            }
                            $count++;
                        }
                    }
                }
                break;
                
            case 'single':
                // Prefetch current and next image
                $year = $params['year'];
                $month = $params['month'];
                $day = $params['day'];
                $image_filename = $params['image_filename'];
                $next_image = $params['next_image'] ?? null;
                
                // Prefetch the current image
                $prefetch_images[] = "$year/$month/$day/$image_filename";
                
                // Prefetch next image if available
                if ($next_image) {
                    $next_img_datepart = $this->getYYYYMMDDHHMMSS($next_image);
                    [$next_year, $next_month, $next_day] = $this->splitImageFilename($next_img_datepart);
                    $prefetch_images[] = "$next_year/$next_month/$next_day/$next_img_datepart.jpg";
                }
                break;
        }
        
        return $prefetch_images;
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
