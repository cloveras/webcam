# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

PHP gallery for webcam images stored as `YYYY/MM/DD/YYYYMMDDHHMMSS.jpg`. No build step — deploy PHP files directly to a web server. Live example: [lilleviklofoten.no/webcam](https://lilleviklofoten.no/webcam/).

## Architecture

- `WebcamConfig.php` — all configuration constants (lat/lon, display periods)
- `SunCalculator.php` — sunrise/sunset/dawn/dusk calculations, midnight sun and polar night logic
- `ImageFileManager.php` — filesystem operations for finding/organizing images
- `NavigationHelper.php` — navigation URL generation
- `webcam.php` — main entry point and HTML rendering
- `aurora.php` — northern lights gallery (reads `aurora.json`, same navigation style as webcam.php)
- `aurora_scan.py` — scans image directories and scores each image for aurora likelihood

## Debug mode

Set `$debug = 1` in `webcam.php`.

## Northern lights (aurora)

`aurora_scan.py` scans a directory of webcam images and scores each for aurora likelihood using OpenCV (green hue, local contrast, connected-component structure). `aurora.php` automatically loads all `aurora-YYYY.json` files it finds in the same directory.

Run per month (fast) or per year (full rebuild). When the output file already exists, the scanned month(s) are replaced and the rest is kept:

```bash
# Update a single month (fast)
python3 aurora_scan.py /path/to/images/2026/03 --night --threshold 0.15 --json-output data/aurora-2026.json

# Full year scan (slow, use for initial build)
python3 aurora_scan.py /path/to/images/2026 --night --threshold 0.15 --json-output data/aurora-2026.json
```

- `--threshold` — minimum score to include (0.15 is a reasonable starting point)
- `--night` — only scan images taken during darkness (before nautical dawn / after nautical dusk, accounts for midnight sun and polar night)
- `--limit N` — cap the stdout report at N results (does not affect JSON output)

Dependencies: `opencv-python`, `numpy`, `astral` (install in a venv).

`sun_calculator.py` is a shared Python module that mirrors `SunCalculator.php` — same location constants, same midnight sun / polar night periods, same nautical twilight (12°) for dawn/dusk. Both scripts stay consistent this way.

## People gallery

`people_scan.py` scans webcam images for people using YOLOv8. Three complementary false-positive suppression layers: civil-twilight time filter, static exclusion zones, and background subtraction.

**First run — build background model once** (samples 300 frames, computes per-pixel median):

```bash
python3 people_scan.py /path/to/images/2026 --build-background data/background-2026.png
```

**Scan** (re-run whenever new months need updating):

```bash
python3 people_scan.py /path/to/images/2026 --civil-day --threshold 0.3 \
    --background data/background-2026.png \
    --exclude-zone 0.0,0.0,1.0,0.68 \
    --exclude-zone 0.52,0.70,0.61,0.81 \
    --exclude-zone 0.40,0.88,0.46,0.99 \
    --json-output data/people-2026.json
```

Exclusion zones (fractions of image width/height): sky/mountains/water, boathouse, foreground poles.

**Diagnose a false positive** — annotates a single image showing all YOLO boxes, zone overlays, and foreground mask:

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
- Images are 3840×2160 (4K 16:9) for 2026+; 2025 images are 2560×1920 (4:3)

Dependencies: `ultralytics`, `astral`, `opencv-python`, `numpy` (install in a venv).

`people.php` reads all `people-YYYY.json` files in the same directory.

## Image maintenance

```bash
python3 delete_old_images.py                          # Dry-run: list deletable images
python3 delete_old_images.py --delete --one-per-hour  # Delete + keep 1/hour
python3 delete_old_images.py --compress-quality 80    # Compress images (requires Pillow)
```
