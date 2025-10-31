<?php
/**
 * Configuration class for webcam application
 * 
 * Contains all site-specific settings and constants.
 * Modify these values for your specific webcam installation.
 */
class WebcamConfig {
    
    // Location settings (Årstrandveien 663, 8314 Gimsøysand, Norway)
    public const LATITUDE = 68.3300814;  // North
    public const LONGITUDE = 14.0917529; // East
    
    // Midnight sun period (tested with date_sunrise() and GPS coordinates on yr.no)
    // Format: ['start' => ['month' => M, 'day' => D], 'end' => ['month' => M, 'day' => D]]
    public const MIDNIGHT_SUN_PERIOD = [
        'start' => ['month' => 5, 'day' => 24],
        'end' => ['month' => 7, 'day' => 18]
    ];
    
    // Polar night period (tested with date_sunrise() and GPS coordinates on yr.no)
    public const POLAR_NIGHT_PERIOD = [
        'start' => ['month' => 12, 'day' => 6],
        'end' => ['month' => 1, 'day' => 6]
    ];
    
    // Polar night fake times (when to show images during polar night)
    public const POLAR_NIGHT_FAKE_SUNRISE_HOUR = 8;
    public const POLAR_NIGHT_FAKE_SUNSET_HOUR = 15;
    public const POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS = 2;
    
    // Image dimensions
    public const LARGE_IMAGE_WIDTH = 900;
    public const LARGE_IMAGE_HEIGHT = 750;
    public const MINI_IMAGE_WIDTH = 160;
    public const MINI_IMAGE_HEIGHT = 120;
    
    // Display settings
    public const MONTHLY_DAY = 15;  // Day to use for full month view
    public const MONTHLY_HOUR = 12; // Hour to use when showing full months
    public const MAX_IMAGES = 1000; // Maximum images to display
    
    // Time settings
    public const TIMEZONE = 'Europe/Oslo';
    public const LOCALE = 'en_US';
    
    // Site information
    public const SITE_NAME = 'Lillevik Lofoten';
    public const SITE_URL = 'https://lilleviklofoten.no';
    public const WEBCAM_URL = 'https://lilleviklofoten.no/webcam/';
    public const SITE_DESCRIPTION = 'Lofoten webcam with view towards west from Vik, Gimsøy, Lofoten, Norway.';
    
    // Filename prefix for files that need renaming (cron hasn't processed yet)
    public const FILENAME_PREFIX_TO_RENAME = 'Lillevik Lofoten_01_';
    
    // First year with images
    public const START_YEAR = 2015;
    
    // Google Analytics and Microsoft Clarity tracking IDs
    public const GOOGLE_ANALYTICS_ID = 'G-P8Z20DT0NR';
    public const MICROSOFT_CLARITY_ID = 'brp4ocus57';
}
