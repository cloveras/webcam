# webcam.php

A self-hosted PHP gallery for webcam images. No database, no build step —
copy the files to your server and you have a full image archive with
dawn-to-dusk filtering, weather, aurora borealis and people detection,
21-language support, and good SEO. Navigates with swipe on phones and
arrow keys on desktops. Loads fast.

[![Live demo](https://img.shields.io/badge/live-lilleviklofoten.no-blue)](https://lilleviklofoten.no/webcam/)

[![Single image view](images/webcam-example-single-image.png)](https://lilleviklofoten.no/webcam/?type=one&image=20260223131133)

---

## Contents

- [Features](#features)
- [Getting started](#getting-started)
- [Aurora borealis gallery](#aurora-borealis-gallery)
- [People gallery](#people-gallery)
- [Bulk image operations](#bulk-image-operations)
- [Performance](#performance)
- [Screenshots](#screenshots)

---

## Features

- Calculates sunrise, sunset, dawn, and dusk from latitude and longitude — only shows images taken between dawn and dusk
- Handles midnight sun and polar night
- Touch gestures and arrow-key navigation
- Time overlay on thumbnails (day, month, and year views)
- Weather from Open-Meteo (historical) and Yr (current day)
- Aurora borealis gallery with live Yr forecast and animated NOAA/SWPC map
- People/vehicle/animal detection gallery powered by YOLOv8
- Client-side image caching, lazy loading, and prefetching
- Responsive images via `srcset` (three sizes) with WebP thumbnails — optimised for mobile and desktop
- Inline CSS, `fetchpriority="high"` on the LCP image, and instant.page + Speculation Rules for fast navigation
- Multilingual UI in 21 languages (en, de, it, fr, nb, nl, es, ja, zh, ko, sv, da, pl, fi, pt, th, tr, id, hi, ms, uk) with auto-detection from browser language, persistent cookie, and SEO hreflang tags

---

## Getting started

### Image directory structure

```
2026/
├── 01/
│   └── 15/
│       ├── 20260115083000.jpg
│       ├── 20260115083010.jpg
│       └── mini/
│           └── 20260115083000.jpg   ← 160×120 thumbnail
└── 02/
    └── ...
```

### Setup

1. Copy all PHP files to your web server.
2. Edit [`WebcamConfig.php`](WebcamConfig.php) — set your coordinates, timezone, analytics IDs, and filename prefix.
3. Edit [`cron/copy-latest-image.sh`](cron/copy-latest-image.sh) and [`cron/rename_and_make_mini_images.sh`](cron/rename_and_make_mini_images.sh) for your camera's filename format, then add them to cron. See [`util/crontab.txt`](util/crontab.txt).
4. Verify calculated sunrise/sunset at [yr.no](https://www.yr.no/).
5. Update the midnight sun and polar night date ranges in [`WebcamConfig.php`](WebcamConfig.php) if applicable.

### Localising site-specific strings

`lang.php` contains translations for 21 languages, but several keys — `webcam_intro`, `seo_description`, `seo_description_short` — contain place names and URLs specific to Lillevik Lofoten. To deploy for a different location, override them before including `webcam.php`:

```php
define('CAM_WEBCAM_INTRO',          '<a href=".">Webcam</a> at My Place, My Town, My Country.');
define('CAM_SEO_DESCRIPTION',       'Live webcam at My Place, My Town. Updated every 10 minutes.');
define('CAM_SEO_DESCRIPTION_SHORT', 'Live webcam at My Place. Updated every 10 minutes.');
require_once __DIR__ . '/webcam.php';
```

These override the translated version for all languages. All other translation keys (navigation labels, weather terms, month names, etc.) are generic and need no changes.

### Code structure

- `webcam.php` — main entry point and HTML rendering
- `WebcamConfig.php` — all configuration constants
- `SunCalculator.php` — sunrise/sunset/dawn/dusk, midnight sun, polar night
- `ImageFileManager.php` — finding and organizing image files
- `NavigationHelper.php` — navigation URL generation
- `lang.php` — multilingual support (21 languages, auto-detection, hreflang SEO)
- `aurora.php` — northern lights gallery
- `people.php` — people/vehicle/animal detection gallery
- `aurora_scan.py` — scores images for aurora likelihood
- `people_scan.py` — detects people using YOLOv8
- `sun_calculator.py` — Python mirror of `SunCalculator.php`, used by the scan scripts

See [`CODE_STRUCTURE.md`](CODE_STRUCTURE.md) for full class documentation.

---

## Aurora borealis gallery

`aurora_scan.py` scores each image for aurora likelihood using OpenCV. Results above `--threshold` are saved to `aurora-YYYY.json` and shown by `aurora.php`, which also displays a live Yr forecast and an animated NOAA/SWPC polar map for the current month.

### How scoring works

Each image is decoded at quarter resolution, then the bottom 35% (ground, sea, lights) is discarded. The remaining sky region is converted to HSV and scored on four signals:

**1. Green/teal pixel coverage** — the fraction of sky pixels that fall within aurora hue ranges. Two ranges are scored separately and the higher wins:

- Classic green: H 38–85 (yellow-green), S ≥ 55, V ≥ 25
- Teal/cyan: H 38–100 (extends into cyan), S ≥ 55, V ≥ 25

The hue cap at H = 100 is deliberate. Pre-dawn and post-dusk twilight sky at high latitudes sits at H 100–130 (blue). Capping at 100 rejects it without affecting real aurora.

**2. Local contrast** — mean absolute deviation between each pixel's brightness (V channel) and a Gaussian-blurred version of itself. Aurora has visible structure and texture; smooth overcast sky scores near zero.

**3. Connected component size** — aurora forms patches and bands, so large connected regions of matched pixels are a positive signal. The contribution is capped at 20% of image area: a single blob covering more than that is more likely background sky than an aurora band.

**4. Global green cast** — the mean of `G − (R + B) / 2` across the sky. A sky that is uniformly green-shifted (green overcast) is penalised.

These are combined linearly:

```
score = green_ratio × 1.8
      + local_contrast × 1.2
      + min(largest_cc_ratio, 0.20) × 1.5
      − global_green_cast × 0.8
```

**Brightness factor** — the whole score is multiplied by a factor that approaches zero as the mean sky brightness rises above ~0.18 (normalised). This suppresses high-latitude spring/autumn twilight images where the sky is still lit but the sun is technically below the horizon.

**Time filter** — by default only images taken outside dawn–dusk (9° solar depression) are scanned. Midnight sun months are skipped entirely. Polar night months are always included.

### Dependencies

```bash
python3 -m venv venv && source venv/bin/activate
pip install opencv-python numpy astral
```

### Scanning

```bash
# Update one month (fast — good for routine use)
python3 aurora_scan.py /path/to/images/2026/03 --threshold 0.15 --json-output data/aurora-2026.json

# Full year (slow — use for initial build)
python3 aurora_scan.py /path/to/images/2026 --threshold 0.15 --json-output data/aurora-2026.json

# Daily incremental update
python3 aurora_scan.py /path/to/images/2026/03/15 --threshold 0.15 --append --json-output data/aurora-2026.json
```

When the output file already exists, only the scanned months are replaced — the rest is preserved.

### Options

| Option | Description |
|---|---|
| `--threshold N` | Minimum score to include (0.15 is a good starting point) |
| `--night` / `--day` | Time filter (default: night) |
| `--limit N` | Cap stdout report at N results (JSON output is unaffected) |
| `--workers N` | Parallel workers (default: all cores; use 1–2 for network drives) |
| `--append` | Upsert individual timestamps instead of replacing the whole month |

---

## People gallery

`people_scan.py` detects people, vehicles, and animals using [YOLOv8](https://github.com/ultralytics/ultralytics) (nano model, ~6 MB, downloaded automatically). Score = highest detection confidence in the frame. Results are saved to `people-YYYY.json` and shown by `people.php`.

Three false-positive suppression layers:
1. Civil-twilight time filter — skips images outside usable daylight
2. Static exclusion zones — ignores detections in known-static areas (sky, water, fixed structures)
3. Background subtraction — rejects detections that match the per-pixel median background

### Dependencies

```bash
python3 -m venv venv && source venv/bin/activate
pip install ultralytics astral opencv-python numpy
```

### Scanning

```bash
# Step 1 — build background model once (~800 MB peak RAM)
python3 people_scan.py /path/to/images/2026 --build-background data/background-2026.png

# Step 2 — scan (re-run whenever new images arrive)
python3 people_scan.py /path/to/images/2026 --civil-day --threshold 0.3 \
    --background data/background-2026.png \
    --exclude-zone 0.0,0.0,1.0,0.60 \
    --exclude-zone 0.0,0.60,0.45,0.68 \
    --exclude-zone 0.52,0.70,0.61,0.81 \
    --exclude-zone 0.40,0.88,0.46,0.99 \
    --json-output data/people-2026.json
```

If `--background` points to a non-existent file the model is built automatically before scanning. Exclusion zones are fractions of image width/height — calibrate for your scene using `--annotate`.

### Diagnosing false positives

```bash
python3 people_scan.py /dev/null \
    --annotate /path/to/image.jpg annotated.jpg \
    --background data/background-2026.png \
    --exclude-zone 0.0,0.0,1.0,0.60 \
    --exclude-zone 0.0,0.60,0.45,0.68
```

`--annotate` saves an image showing every YOLO box (green = kept, red = rejected by zone, orange = rejected by background), zone overlays, and the foreground mask.

### Options

| Option | Description |
|---|---|
| `--threshold N` | Minimum confidence to include (0–1; 0.3 is a good starting point) |
| `--civil-day` | Civil twilight filter (6° depression) — fewer low-light false positives than `--day` |
| `--day` | Nautical twilight filter (12° depression) |
| `--background FILE` | Background model PNG; auto-built if missing |
| `--build-background FILE` | Build background model and exit |
| `--exclude-zone x1,y1,x2,y2` | Ignore detections centred in this zone (repeatable) |
| `--annotate IMAGE OUTPUT` | Annotate one image for diagnosis and exit |
| `--fg-overlap N` | Min foreground fraction of detection box (default 0.15) |
| `--bg-diff N` | Pixel diff threshold for foreground detection (default 25) |
| `--bg-samples N` | Frames sampled when building background (default 300) |
| `--limit N` | Cap stdout report at N results (JSON output is unaffected) |
| `--workers N` | Parallel workers (default: all cores; use 1–2 for network drives) |
| `--append` | Upsert individual timestamps instead of replacing the whole month |

---

## Bulk image operations

[`util/webcam-image-organize-fix.sh`](util/webcam-image-organize-fix.sh) reorganizes images into `YYYY/MM/DD` directories.

[`util/nctpput-all-images.sh`](util/nctpput-all-images.sh) mass-uploads files using [`ncftp`](https://www.ncftp.com).

[`util/delete_old_images.py`](util/delete_old_images.py) thins out old images (dry-run by default):

```bash
python3 util/delete_old_images.py --delete --one-per-hour   # keep one per hour
python3 util/delete_old_images.py --compress-quality 80     # recompress (requires Pillow)
```

---

## Performance

The main webcam page is optimised for fast load times:

| Technique | Details |
|---|---|
| Responsive latest image | `srcset` with three sizes (650w / 900w / 1800w) generated by cron — browser picks the right one |
| WebP thumbnails | `<picture>` element with WebP source and JPEG fallback; thumbnails at 300×300 |
| Inline CSS | CSS is inlined at render time — eliminates the render-blocking `<link rel="stylesheet">` request |
| LCP hint | `fetchpriority="high"` on the main image so the browser fetches it first |
| Prefetch | `<link rel="prefetch">` for previous and next pages (and their images), fired immediately on page load |
| Prerender | [Speculation Rules API](https://developer.chrome.com/docs/web-platform/prerender-pages) prerenders prev/next in Chrome/Edge; silently ignored in other browsers |
| Hover prefetch | [instant.page](https://instant.page) prefetches links on hover (~300 ms head start) in all browsers |

Google PageSpeed scores for [lilleviklofoten.no/webcam](https://lilleviklofoten.no/webcam/):

| | Performance | Accessibility | Best Practices | SEO |
|---|---|---|---|---|
| Mobile | 90 | 93 | 100 | 100 |
| Desktop | 98 | 93 | 100 | 100 |

---

## Screenshots

Single image:

[![Single image view](images/webcam-example-single-image.png)](https://lilleviklofoten.no/webcam/?type=one&image=20260223131133)

Full day — all images from dawn to dusk, with time overlay:

[![Day view](images/webcam-example-day.png)](https://lilleviklofoten.no/webcam/?type=day&date=20260223)

Full month — one image per day at ~12:00:

[![Month view](images/webcam-example-month.png)](https://lilleviklofoten.no/webcam/?type=month&year=2026&month=01)

---

If you find this useful, [buy me a coffee](https://www.buymeacoffee.com/superelectric) ☕️
