# Webcam.php Code Structure

## File Structure

### Core Files

- `webcam.php` - Main entry point and page rendering functions
- `WebcamConfig.php` - All configuration constants and site-specific settings
- `SunCalculator.php` - Sun time calculations (sunrise, sunset, dawn, dusk, midnight sun, polar night)
- `ImageFileManager.php` - File system operations for images
- `NavigationHelper.php` - Navigation and URL generation helpers
- `lang.php` - Multilingual support: translations, language detection, helper functions
- `css.php` - CSS file server (serves `webcam.css` with versioned cache-busting via `?v=<mtime>`)
- `latest.php` - Latest image server with no-cache headers

## Class Documentation

### WebcamConfig

Central configuration class containing all site-specific settings:

- Location coordinates (latitude/longitude)
- Midnight sun and polar night periods
- Image dimensions
- Display settings (monthly view defaults)
- Timezone and locale
- Site information and URLs
- Analytics tracking IDs

To customize for your installation: Edit the constants in `WebcamConfig.php`.

### SunCalculator

Handles all astronomical calculations:

- `isMidnightSun($timestamp)` - Check if date is in midnight sun period
- `isPolarNight($timestamp)` - Check if date is in polar night period
- `findSunTimes($timestamp)` - Calculate sunrise, sunset, dawn, and dusk times

Key features:
- Automatically handles midnight sun (shows images 00:00-23:59)
- Automatically handles polar night (shows images 06:00-17:00)
- Adjusts dawn/dusk to stay within the same day as sunrise/sunset
- Uses PHP's built-in `date_sun_info()` for accurate calculations

### ImageFileManager

Manages all file system operations:

- `splitImageFilename($filename)` - Extract date/time components
- `getYYYYMMDDHHMMSS($fullPath)` - Get date part from filename
- `findLatestImage()` - Find the most recent image
- `findFirstDayWithImages($year, $month)` - Find first available day
- `getAllImagesInDirectory($directory)` - Get all images for a day
- `findFirstImageAfterTime(...)` - Find first image after specific time
- `findClosestImageToHour($directory, $hour)` - Find image nearest to a target hour (fallback for days with gaps)
- `checkAndRenameFilesHack($prefix)` - Handle files before cron processes them

### NavigationHelper

Navigation and URL generation utilities:

- `findPreviousAndNextMonth($year, $month)` - Calculate adjacent months
- `buildUrl($type, $params)` - Generate URLs with query parameters

## Page Types

The application supports several view types via the `?type=` parameter:

- `last` - Latest single image (default)
- `one` - Specific single image (`&image=YYYYMMDDHHMMSS`)
- `day` - All images for a day (`&date=YYYYMMDD&size=mini|large`)
- `month` - One image per day for a month (`&year=YYYY&month=MM`)
- `year` - Multiple images per month for a year (`&year=YYYY`)
- `all` - Overview of all years (heavy operation)

## No External Dependencies

The application uses only PHP standard library functions.

## Customization Guide

To use this code for your own webcam:

1. Update `WebcamConfig.php`:
   - Set `LATITUDE` and `LONGITUDE` for your location
   - Update `MIDNIGHT_SUN_PERIOD` and `POLAR_NIGHT_PERIOD` dates (check [yr.no](https://www.yr.no/))
   - Change `SITE_NAME`, `SITE_URL`, `SITE_DESCRIPTION`
   - Update `GOOGLE_ANALYTICS_ID` and `MICROSOFT_CLARITY_ID`
   - Set `FILENAME_PREFIX_TO_RENAME` for your camera's filename format
   - Adjust `START_YEAR` to your first year of images

2. Update `lang.php`:
   - Edit the `'en'` translation block — all other languages are auto-translated
   - `seo_description` is used in `<meta name="description">` and `<meta property="og:description">`
   - `seo_description_short` is shown as a visible paragraph below single images
   - `webcam_intro` / `aurora_intro` are used as the first line of each page

3. Update `webcam.php`:
   - Update Schema.org structured data in `page_header()`
   - Customize the `print_lillevik_images_and_links()` function for your site

3. Test thoroughly:
   - Verify sunrise/sunset times at [yr.no](https://www.yr.no/)
   - Test navigation during polar night and midnight sun periods
   - Check image display for different view types

## License

Apache-2.0
