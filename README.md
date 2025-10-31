# webcam.php

Generates HTML pages
([examples here](#example-screenshots))
for webcam images stored in a directory structure like this:
`YYYY/MM/DD/YYYYMMDDHHMMSS.jpg`:

```
2023
├── 01
│   ├── 01
│   │   ├── 20230101000000.jpg
│   │   ├── 20230101001000.jpg
│   │   ├── 20230101002000.jpg
[...]
├── 12
│   │   ├── 20231231000000.jpg
│   │   ├── 20231231001000.jpg
│   │   ├── 20231231002000.jpg
[...]
```

## Highlights

* Finds sunrise, sunset, dawn, and dusk based on latitude and longitude.
* Only shows images taken between dawn and dusk, handles midnight sun and polar night.
* Navigation with touch gestures and arrow keys.
* Shows the time (HH:MM) as a CSS overlay on thumbails when viewing a full day, month and year.
* Data collection with Google Analytics and Microsoft Clarity
* **Performance optimizations**: Client-side caching, resource prefetching, and optimized loading (see [PERFORMANCE.md](PERFORMANCE.md))

Example: [Lillevik Lofoten webcam](https://lilleviklofoten.no/webcam/?type=day&date=20231117)

If you like this you can
[buy me a coffee](https://www.buymeacoffee.com/superelectric) ☕️
(you'll be the first)

## Things that should be changed if you want to use this

* Edit
  [`copy-latest-image.sh`](https://github.com/cloveras/webcam/blob/main/cron/copy-latest-image.sh)
  and
  [`rename_and_make_mini_images.sh`](https://github.com/cloveras/webcam/blob/main/cron/rename_and_make_mini_images.sh).
* Add cron jobs for those two scripts. See
  [crontab.txt](util/crontab.txt).
* Edit the filename in `check_and_rename_files_hack()` that covers for cron when it's too slow.
* Update latitude and longitude (use Google Maps to find coordinates)
* Verify the calculated sunrise and sunset at [yr.no](https://www.yr.no/).
* Update the dates in functions `midnight_sun()` and `polar_night()`.
* Change the code for Google and Analytics Microsoft Clarity.
* Update the HTML meta tags.

For verbose feedback for debugging: Set `$debug = 1` in `webcam.php`.

## Code Structure (October 2025 Refactoring)

The code has been refactored for better maintainability while preserving 100% backward compatibility:

* **`WebcamConfig.php`** - All configuration constants (location, periods, display settings)
* **`SunCalculator.php`** - Sun time calculations (sunrise, sunset, dawn, dusk, handles midnight sun and polar night)
* **`ImageFileManager.php`** - File system operations for finding and organizing images
* **`NavigationHelper.php`** - Navigation and URL generation utilities
* **`webcam.php`** - Main entry point with page rendering functions (now with PHPDoc comments)

See [`CODE_STRUCTURE.md`](CODE_STRUCTURE.md) for detailed documentation of the refactored code structure.

**Key improvements:**
- Clear separation of concerns
- Centralized configuration
- Well-documented classes and functions
- Easier customization and maintenance
- No external dependencies
- All original behavior preserved

## Got lots of images you need to sort and upload?

The Bash script
[`webcam-image-organize-fix.sh`](https://github.com/cloveras/webcam/blob/main/util/webcam-image-organize-fix.sh)
can be a good _starting point_
for reorganizing thousands of images into `YYYY/MM/DD` directories.

The Bash script
[`util/nctpput-all-images.sh`](https://github.com/cloveras/webcam/blob/main/util/nctpput-all-images.sh)
uses
[`ncftp`](https://www.ncftp.com)
and can be a good _starting point_ for mass-uploading thousands of files.

## Need to delete old images to save disk space?

The Python script
[`delete_old_images.py`](https://github.com/cloveras/webcam/blob/main/delete_old_images.py)
can help you delete old webcam images that fall outside the display interval (dawn to dusk).
These images are not shown on the website and can be safely deleted to free up disk space.

**Features:**
- Calculates sunrise/sunset/dawn/dusk times for each day based on your location
- Identifies images that fall outside the display interval
- **NEW:** Keep only one photo per hour (closest to whole hour) to significantly reduce storage
- **NEW:** Compress remaining images to save additional space (e.g., 80% quality)
- Dry-run mode by default (lists files without deleting)
- Can filter by specific year/month (e.g., `2018/03`)
- Can filter by minimum age (e.g., only process images older than 5 years)
- Handles both full-size and mini images

**Usage examples:**
```bash
# Dry run: List files to delete for all years older than 5 years
python3 delete_old_images.py

# Actually delete files for all years older than 5 years
python3 delete_old_images.py --delete

# Process only images from March 2018
python3 delete_old_images.py --year-filter 2018/03

# Process images older than 3 years
python3 delete_old_images.py --min-age-years 3

# Delete images from January 2018
python3 delete_old_images.py --delete --year-filter 2018/01

# Keep only one photo per hour (closest to whole hour) for old images
python3 delete_old_images.py --one-per-hour

# Compress remaining images to quality 80% to save additional space
python3 delete_old_images.py --compress-quality 80

# Combine all space-saving features for maximum storage reduction
python3 delete_old_images.py --delete --one-per-hour --compress-quality 80
```

**Note:** Image compression requires the Pillow library. Install it with:
```bash
pip3 install Pillow
```

## Example screenshots

Resized to fit.

A single image: https://lilleviklofoten.no/webcam/?type=one&image=20231126151113

![Webcam example screenshot: Single image](images/webcam-example-single-image.png)

One full day: https://lilleviklofoten.no/webcam/?type=day&date=20231126

![Webcam example screenshot: Day](images/webcam-example-day.png)

One full month: https://lilleviklofoten.no/webcam/?type=month&year=2023&month=11

![Webcam example screensho: Month](images/webcam-example-month.png)
