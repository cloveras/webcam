# Webcam.php Code Structure

## Overview

The webcam.php application has been refactored to improve maintainability while preserving 100% backward compatibility. The code is now organized into logical classes with clear responsibilities.

## File Structure

### Core Files

- **`webcam.php`** - Main entry point and page rendering functions
- **`WebcamConfig.php`** - All configuration constants and site-specific settings
- **`SunCalculator.php`** - Sun time calculations (sunrise, sunset, dawn, dusk, midnight sun, polar night)
- **`ImageFileManager.php`** - File system operations for images
- **`NavigationHelper.php`** - Navigation and URL generation helpers
- **`css.php`** - CSS file server
- **`latest.php`** - Latest image server with no-cache headers

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

**To customize for your installation:** Edit the constants in `WebcamConfig.php`.

### SunCalculator

Handles all astronomical calculations:

- `isMidnightSun($timestamp)` - Check if date is in midnight sun period
- `isPolarNight($timestamp)` - Check if date is in polar night period  
- `findSunTimes($timestamp)` - Calculate sunrise, sunset, dawn, and dusk times

**Key features:**
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
- `checkAndRenameFilesHack($prefix)` - Handle files before cron processes them

### NavigationHelper

Navigation and URL generation utilities:

- `findPreviousAndNextMonth($year, $month)` - Calculate adjacent months
- `buildUrl($type, $params)` - Generate URLs with query parameters

## Page Types

The application supports several view types via the `?type=` parameter:

- **`last`** - Latest single image (default)
- **`one`** - Specific single image (`&image=YYYYMMDDHHMMSS`)
- **`day`** - All images for a day (`&date=YYYYMMDD&size=mini|large`)
- **`month`** - One image per day for a month (`&year=YYYY&month=MM`)
- **`year`** - Multiple images per month for a year (`&year=YYYY`)
- **`all`** - Overview of all years (heavy operation)

## Key Design Decisions

### Backward Compatibility

All original function names are preserved as wrapper functions that delegate to the new classes. This ensures:

- No breaking changes to existing code
- Gradual migration path
- Easy rollback if needed

### No External Dependencies

The refactoring uses only PHP standard library functions to maintain simplicity and avoid dependency management.

### Timing Logic Preservation

The most critical aspect - sun time calculations for polar night and midnight sun - has been carefully preserved and tested. The logic matches the original behavior exactly.

### Configuration Centralization

All site-specific values are now in `WebcamConfig.php`, making it easy to:
- See what needs to be changed for a new installation
- Update tracking IDs
- Modify timing parameters
- Adjust image dimensions

## Testing

A test script `/tmp/test_sun_times.php` verifies sun time calculations throughout the year, including:

- Polar night transitions (December 6 - January 6)
- Midnight sun transitions (May 24 - July 18)
- Normal days with calculated sunrise/sunset
- Edge cases at period boundaries

## Customization Guide

To use this code for your own webcam:

1. **Update `WebcamConfig.php`:**
   - Set `LATITUDE` and `LONGITUDE` for your location
   - Update `MIDNIGHT_SUN_PERIOD` and `POLAR_NIGHT_PERIOD` dates (check [yr.no](https://www.yr.no/))
   - Change `SITE_NAME`, `SITE_URL`, `SITE_DESCRIPTION`
   - Update `GOOGLE_ANALYTICS_ID` and `MICROSOFT_CLARITY_ID`
   - Set `FILENAME_PREFIX_TO_RENAME` for your camera's filename format
   - Adjust `START_YEAR` to your first year of images

2. **Update `webcam.php`:**
   - Modify the HTML meta tags in `page_header()` function
   - Update Schema.org structured data
   - Customize the `print_lillevik_images_and_links()` function for your site

3. **Test thoroughly:**
   - Verify sunrise/sunset times at [yr.no](https://www.yr.no/)
   - Test navigation during polar night and midnight sun periods
   - Check image display for different view types

## Future Improvements

Potential enhancements that maintain backward compatibility:

- Extract HTML generation into template system
- Add caching layer for expensive operations
- Implement proper error handling and logging
- Add unit tests for each class
- Create admin interface for configuration
- Add support for multiple cameras

## License

Apache-2.0 (same as original project)
