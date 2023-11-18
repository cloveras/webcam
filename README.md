# webcam.php

Generates HTML pages
([examples here](#example-screenshots))
for webcam images stored in a directory structure like this:
`YYYY/MM/DD/YYYYMMDDHHMMSS.jpg`:

```
2023
├── 01
│   ├── 01
│   │   ├── 20230101032215.jpg
│   │   ├── 20230101032721.jpg
│   │   ├── 20230101033228.jpg
[...]
├── 11
│   ├── 17
│   │   ├── 20231117000232.jpg
│   │   ├── 20231117001233.jpg
│   │   ├── 20231117002233.jpg
[...]
```

## Highlights

* Finds sunrise, sunset, dawn, and dusk based on latitude and longitude.
* Only shows images taken between dawn and dusk, handles midnight sun and polar night.
* Navigation with touch gestures and arrow keys.
* Data collection with Google Analytics and Microsoft Clarity

Example: [Lillevik Lofoten webcam](https://lilleviklofoten.no/webcam/?type=day&date=20231117)

## Todo

* Add a CSS overlay to display `HH:MM` in the lower right corner on the mini images shown for days, months and years.

## Things that should be changed if you want to use this

* Add cron jobs for `copy-latest-image.sh` and `rename_and_make_mini_images.sh`.
* Edit the filename in `check_and_rename_files_hack()` that covers for cron when it's too late.
* Update latitude and longitude (use Google Maps to find coordinates)
* Verify the calculated sunrise and sunset at [yr.no](https://www.yr.no/).
* Update the dates in functions `midnight_sun()` and `polar_night()`.
* Change the code for Google and Analytics Microsoft Clarity.
* Update the HTML meta tags.

For verbose feedback for debugging: `$debug = 1`.

Please note: The script started as a simple hack, then grew into this much larger
and barely maintainable hack.  It works well, but the code is not super elegant.
PRs are welcome.

## Got lots of images you need to sort?

The Bash script `webcam-image-organize-fix.sh` can be a good _starting point_
for reorganizing thousands of images into `YYYY/MM/DD` directories.

## Example screenshots

A single image: https://lilleviklofoten.no/webcam/?type=one&image=20231117095251

![Webcam example screenshot: Single image](images/webcam-example-single-image.png)

One full day: https://lilleviklofoten.no/webcam/?type=day&date=20231117

![Webcam example screenshot: Day](images/webcam-example-day.png)

One full month: https://lilleviklofoten.no/webcam/?type=month&year=2023&month=11

![Webcam example screensho: Month](images/webcam-example-month.png)
