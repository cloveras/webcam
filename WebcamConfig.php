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
    // Filename prefix for files that need renaming (cron hasn't processed yet)
    public const FILENAME_PREFIX_TO_RENAME = 'Lillevik Lofoten_01_';
    
    // First year with images
    public const START_YEAR = 2015;
    
    // Google Analytics and Microsoft Clarity tracking IDs
    public const GOOGLE_ANALYTICS_ID = 'G-P8Z20DT0NR';
    public const MICROSOFT_CLARITY_ID = 'brp4ocus57';

    // Open-Meteo (https://open-meteo.com) is used for historical daily weather — no API key needed.

    // Yr.no location code — find yours at https://www.yr.no (copy from the URL of your location)
    public const YR_LOCATION_CODE = '1-279560';

    // Contact information (used in JSON-LD structured data)
    public const CONTACT_EMAIL = 'post@lofotenvacation.no';
    public const CONTACT_PHONE = '+4741130944';

    // Physical address (used in JSON-LD structured data)
    public const ADDRESS_STREET  = 'Årstrandveien 663';
    public const ADDRESS_POSTAL  = '8314';
    public const ADDRESS_CITY    = 'Gimsøysand';
    public const ADDRESS_REGION  = 'Nordland';
    public const ADDRESS_COUNTRY = 'NO';

    // Logo and favicon URLs (used in JSON-LD and <link> tags)
    public const LOGO_URL        = 'https://lilleviklofoten.no/logo/lillevik-logo-1000.jpg';
    public const FAVICON_32      = '/wp-content/uploads/2020/08/cropped-lillevik-drone-001-20200613-0921-21-2-scaled-2-32x32.jpg';
    public const FAVICON_192     = '/wp-content/uploads/2020/08/cropped-lillevik-drone-001-20200613-0921-21-2-scaled-2-192x192.jpg';
    public const FAVICON_180     = '/wp-content/uploads/2020/08/cropped-lillevik-drone-001-20200613-0921-21-2-scaled-2-180x180.jpg';

    // Social media and booking profiles (used in JSON-LD sameAs)
    public const SOCIAL_PROFILES = [
        'https://facebook.com/lilleviklofoten',
        'https://instagram.com/lilleviklofoten',
        'https://www.tiktok.com/@lilleviklofoten',
        'https://www.reddit.com/user/Lillevik_Lofoten/',
        'https://bsky.app/profile/lilleviklofoten.bsky.social',
        'https://www.booking.com/hotel/no/lillevik-lofoten.html',
        'https://www.airbnb.com/rooms/44385543',
        'https://lofotenvacation.com/en/lillevik-lofoten',
        'https://maps.app.goo.gl/nKPJn2wFm5uWBZTg7',
        'https://maps.apple.com/?ll=68.330081,14.091728&q=Lillevik%20Lofoten',
    ];

    // Fallback SERVER_NAME for command-line use (php webcam.php)
    public const FALLBACK_SERVER_NAME = 'lilleviklofoten.no';
}
