# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

PHP gallery for webcam images stored as `YYYY/MM/DD/YYYYMMDDHHMMSS.jpg`. No build step — deploy PHP files directly to a web server. Live examples:
- [lilleviklofoten.no/webcam](https://lilleviklofoten.no/webcam/) — Lillevik (primary, public)
- [lilleviklofoten.no/webcam/viktun/](https://lilleviklofoten.no/webcam/viktun/) — Viktun (password-protected)

## Architecture

- `WebcamConfig.php` — all configuration constants (lat/lon, display periods)
- `SunCalculator.php` — sunrise/sunset/dawn/dusk calculations, midnight sun and polar night logic
- `ImageFileManager.php` — filesystem operations for finding/organizing images
- `NavigationHelper.php` — navigation URL generation
- `lang.php` — multilingual support: `t()`, `t_month()`, `t_month_year()`, `t_month_day()`, `lang_param()`, `lang_query()`, `lang_selector_html()`, `lang_hreflang_links()`
- `webcam.php` — main entry point and HTML rendering; supports multiple cameras via `define()` guards
- `aurora.php` — northern lights gallery (reads `aurora-YYYY.json` files from `data/`)
- `people.php` — people detection gallery (reads `people-YYYY.json` files); supports multiple cameras via `define()` guards
- `aurora_scan.py` — scans image directories and scores each image for aurora likelihood
- `people_scan.py` — scans webcam images for people/vehicles/animals using YOLOv8

## Multi-camera support

`webcam.php` and `people.php` use `defined() || define()` guards so camera-specific constants can be set before including the shared file. Each camera gets a thin wrapper:

### Viktun (`viktun/webcam.php`, `viktun/people.php`)

```php
define('CAM_LABEL',           'Viktun webcam');
define('CAM_FILE_PREFIX',     'Viktun_01_');
define('CAM_FILE_PREFIX_ALT', '');
define('CAM_IS_PRIMARY',      false);   // suppresses JSON-LD, canonical, Lillevik-specific blocks
define('CAM_SHOW_PEOPLE',     true);    // show People link in nav
define('CAM_CSS_PATH',        '../css.php');
define('CAM_INTRO_HTML',      '<a href=".">Webcam</a> at Viktun. See also: <a href="../">Lillevik webcam</a>.');
require_once __DIR__ . '/../webcam.php';
```

Available `CAM_*` constants (webcam.php):
- `CAM_LABEL` — page title / h1
- `CAM_FILE_PREFIX` / `CAM_FILE_PREFIX_ALT` — prefixes stripped from uploaded filenames
- `CAM_IS_PRIMARY` — enables JSON-LD, canonical tag, hreflang, SEO paragraph, Lillevik-specific nav blocks
- `CAM_SHOW_PEOPLE` — show People link in nav (default: `false`)
- `CAM_CSS_PATH` — path to css.php (default: `css.php`)
- `CAM_INTRO_HTML` — intro paragraph HTML (non-primary cameras only; primary uses `t('webcam_intro')` from lang.php)

Available `PEOPLE_*` constants (people.php):
- `PEOPLE_LABEL` — page title
- `PEOPLE_CSS_PATH` — path to css.php
- `PEOPLE_INTRO_HTML` — intro paragraph HTML
- `PEOPLE_SHOW_AURORA` — show Aurora link in nav (default: `true`)
- `PEOPLE_DATA_DIR` — absolute path to directory containing `people-YYYY.json` files

Navigation links (aurora.php, people.php) use `file_exists()` to auto-show/hide per camera.

## Multilingual support

`lang.php` provides translations for 21 languages: `en de it fr nb nl es ja zh ko sv da pl fi pt th tr id hi ms uk`.

Language detection order: `?lang=XX` query param (sets 1-year cookie) → cookie → `Accept-Language` header → English.

Short URLs `/webcam/XX/` redirect to `?lang=XX` via `.htaccess`.

Key functions:
- `t(string $key)` — returns translated string; auto-localises `lilleviklofoten.no` hrefs for non-English
- `t_month_year(int $month, int $year)` — localised "Month Year" (CJK uses `年月` format)
- `t_month_day(int $month, int $day)` — localised "Day Month" (handles day-first vs. month-first languages)
- `lang_param()` — returns `"&lang=XX"` or `""` for English; append to nav URLs
- `lang_query()` — returns `"?lang=XX"` or `""` for English; append to bare URLs
- `lang_selector_html()` — inline `<select>` language switcher for nav bar
- `lang_hreflang_links(string $base_url)` — `<link rel="alternate" hreflang>` tags for SEO

Translation keys include all nav labels, month names, weather terms, aurora/people UI strings, and two SEO descriptions: `seo_description` (full, used in `<meta>`) and `seo_description_short` (half-length, shown below single images).

## Debug mode

Set `$debug = 1` in `webcam.php`.

## Northern lights (aurora)

`aurora_scan.py` scans a directory of webcam images and scores each for aurora likelihood using OpenCV (green hue, local contrast, connected-component structure). `aurora.php` automatically loads all `aurora-YYYY.json` files it finds in the same directory.

Defaults: `--threshold 0.15`, `--night` on, JSON output auto-derived from year in folder path.

Run per month (fast) or per year (full rebuild). When the output file already exists, the scanned month(s) are replaced and the rest is kept:

```bash
# Update a single month (fast) — defaults handle threshold, night filter, and output path
python3 aurora_scan.py /path/to/images/2026/03

# Full year scan (slow, use for initial build)
python3 aurora_scan.py /path/to/images/2026

# All years at once
for year in $(seq 2026 -1 2015); do python3 aurora_scan.py /path/to/images/$year; done
```

- `--threshold N` — minimum score to include (default: 0.15)
- `--night` / `--day` — time filter (default: night)
- `--json-output FILE` — override auto-derived output path
- `--limit N` — cap the stdout report at N results (does not affect JSON output)

Dependencies: `opencv-python`, `numpy`, `astral` (install in a venv).

`sun_calculator.py` is a shared Python module that mirrors `SunCalculator.php` — same location constants, same midnight sun / polar night periods, same nautical twilight (12°) for dawn/dusk.

## People gallery

`people_scan.py` scans webcam images for people using YOLOv8. Three complementary false-positive suppression layers: civil-twilight time filter, static exclusion zones, and background subtraction.

JSON files live in `data/` (Lillevik) or `viktun/data/` (Viktun). `people.php` reads all `people-YYYY.json` files in its `PEOPLE_DATA_DIR`.

### Lillevik

Images: 3840×2160 (4K 16:9) for 2026+; 2560×1920 (4:3) for 2025.

```bash
# Build background model once
python3 people_scan.py /path/to/Lillevik-webcam/2026 --build-background data/background-2026.png

# Scan
python3 people_scan.py /path/to/Lillevik-webcam/2026 --civil-day --threshold 0.3 \
    --background data/background-2026.png \
    --exclude-zone 0.0,0.0,1.0,0.68 \
    --exclude-zone 0.52,0.70,0.61,0.81 \
    --exclude-zone 0.40,0.88,0.46,0.99 \
    --json-output data/people-2026.json
```

Exclusion zones: sky/mountains/water, boathouse, foreground poles.

### Viktun

Images: 960×720 (4:3).

```bash
# Build background model once
python3 people_scan.py /path/to/Viktun-webcam/2026 --build-background viktun/data/background-2026.png

# Scan
python3 people_scan.py /path/to/Viktun-webcam/2026 --civil-day --threshold 0.3 \
    --background viktun/data/background-2026.png \
    --exclude-zone 0.0,0.0,1.0,0.50 \
    --exclude-zone 0.0,0.45,0.30,0.68 \
    --json-output viktun/data/people-2026.json
```

Exclusion zones: sky (top 50%), mountain on the left side.

### Diagnose a false positive

```bash
python3 people_scan.py /dev/null \
    --annotate /path/to/image.jpg annotated.jpg \
    --background data/background-2026.png \
    --exclude-zone 0.0,0.0,1.0,0.68 \
    --exclude-zone 0.52,0.70,0.61,0.81 \
    --exclude-zone 0.40,0.88,0.46,0.99
```

- `--civil-day` — civil twilight (6° depression), tighter than `--day` (nautical 12°), fewer low-light false positives
- `--background FILE` — background model PNG; auto-built from scan folder if file doesn't exist
- `--exclude-zone x1,y1,x2,y2` — ignore detections centred in this zone (repeatable)
- `--fg-overlap N` — min fraction of detection box in foreground (default 0.15)

Dependencies: `ultralytics`, `astral`, `opencv-python`, `numpy` (install in a venv).

## Server-side cron (domeneshop.no)

```
*/7  * * * * .../webcam/copy-latest-image.sh
*/10 * * * * .../webcam/rename_and_make_mini_images.sh
*/7  * * * * .../webcam/viktun/copy_latest_viktun_image.sh
*/10 * * * * .../webcam/viktun/rename_viktun_images.sh
```

`rename_viktun_images.sh` strips the `Viktun_01_` prefix and creates 160×120 mini thumbnails.
`copy_latest_viktun_image.sh` copies the latest renamed image to `viktun/latest.jpg`.

## Mac cron (~/SynologyDrive/scripts/)

- `people-scan.sh` — daily 03:00, scans both Lillevik and Viktun, rsyncs JSON to server
- `aurora-scan.sh` — runs every minute, scans new aurora images for Lillevik only
- `mount-k2.sh` — every 5 min, pings k2 NAS and remounts SMB if disconnected

## NAS cron (Synology Task Scheduler)

- `rsync-lillevik-webcam.sh` — pulls Lillevik images from server to NAS
- `rsync-viktun-webcam.sh` — pulls Viktun images from server to NAS

## Image maintenance

```bash
python3 delete_old_images.py                          # Dry-run: list deletable images
python3 delete_old_images.py --delete --one-per-hour  # Delete + keep 1/hour
python3 delete_old_images.py --compress-quality 80    # Compress images (requires Pillow)
```
