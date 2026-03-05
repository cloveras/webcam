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
python3 aurora_scan.py /path/to/images/2026/03 --night --threshold 0.15 --json-output aurora-2026.json

# Full year scan (slow, use for initial build)
python3 aurora_scan.py /path/to/images/2026 --night --threshold 0.15 --json-output aurora-2026.json
```

- `--threshold` — minimum score to include (0.15 is a reasonable starting point)
- `--night` — only scan images between 18:00 and 08:00
- `--limit N` — cap the stdout report at N results (does not affect JSON output)

Dependencies: `opencv-python`, `numpy`, `astral` (install in a venv).

`sun_calculator.py` is a shared Python module that mirrors `SunCalculator.php` — same location constants, same midnight sun / polar night periods, same nautical twilight (12°) for dawn/dusk. Both scripts stay consistent this way.

## Image maintenance

```bash
python3 delete_old_images.py                          # Dry-run: list deletable images
python3 delete_old_images.py --delete --one-per-hour  # Delete + keep 1/hour
python3 delete_old_images.py --compress-quality 80    # Compress images (requires Pillow)
```
