# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This workspace contains several independent hobby projects, all related to the [Lillevik Lofoten webcam](https://lilleviklofoten.no/webcam/) or Norwegian legal data:

| Directory | Language | Purpose |
|-----------|----------|---------|
| `aurora/` | Python | Detect aurora borealis in webcam images using OpenCV |
| `lovdata2/` | Python + YAML | Norwegian law REST API prototype + MCP server for Claude Desktop |
| `webcam/` | PHP | Webcam image gallery generator (lilleviklofoten.no) |
| `webcam-people/` | Python | People detection in webcam images using HOG+SVM |

---

## aurora

Scans a directory of webcam JPG images and scores each for aurora likelihood using color, texture, and connected-component analysis.

**Run:**
```bash
cd aurora
source venv/bin/activate
python aurora_scan.py <folder> [--limit 50] [--threshold 0.1] [--night]
```

**Dependencies:** `opencv-python`, `numpy`. Images must follow the `YYYYMMDDHHMMSS.jpg` naming convention. Mini-images (paths containing "mini") are skipped.

---

## lovdata2

### API Prototype

A conceptual OpenAPI 3.1 spec (`openapi/lovdata-api.yaml`) showing how Norwegian law could be exposed as clean REST. Lint the spec with Spectral:
```bash
cd lovdata2
npm install
npx spectral lint openapi/lovdata-api.yaml
```

### Dataset Pipeline

Run in order to build the local dataset:
```bash
cd lovdata2
python3 -m venv venv && source venv/bin/activate
pip install -r requirements.txt

python scripts/download_raw.py       # Downloads tarballs into raw/
python scripts/prepare_xml.py        # Extracts/normalizes XML into data/xml_pretty/
python scripts/build_dataset.py      # Builds HTML, Markdown, JSON + metadata
```

### MCP Server

Exposes the local dataset to Claude Desktop via the Model Context Protocol.

```bash
cd lovdata2/mcp-lovdata
python3 -m venv venv && source venv/bin/activate
pip install mcp python-dotenv
python server.py  # Claude Desktop spawns this automatically
```

Configure in `~/Library/Application Support/Claude/claude_desktop_config.json` — see `README-mcp.md` for the exact JSON. The `LOVDATA2_DATA_ROOT` env var points to the `data/` directory.

**MCP tools exposed:** `search_lovdata`, `list_documents`, `get_document`, `get_section`, `get_raw_view`.

**Document ID format:** `nl-YYYYMMDD-NNNN` (laws) or `sf-YYYYMMDD-NNNN` (regulations).

---

## webcam

PHP gallery for images stored as `YYYY/MM/DD/YYYYMMDDHHMMSS.jpg`. No build step — deploy PHP files directly to a web server.

**Architecture:**
- `WebcamConfig.php` — all configuration constants (lat/lon, display periods)
- `SunCalculator.php` — sunrise/sunset/dawn/dusk calculations, midnight sun and polar night logic
- `ImageFileManager.php` — filesystem operations for finding/organizing images
- `NavigationHelper.php` — navigation URL generation
- `webcam.php` — main entry point and HTML rendering

**Debug mode:** Set `$debug = 1` in `webcam.php`.

**Image maintenance:**
```bash
python3 delete_old_images.py                          # Dry-run: list deletable images
python3 delete_old_images.py --delete --one-per-hour  # Delete + keep 1/hour
python3 delete_old_images.py --compress-quality 80    # Compress images (requires Pillow)
```

---

## webcam-people

Fetches webcam images from lilleviklofoten.no and detects people using OpenCV HOG+SVM.

```bash
cd webcam-people
pip install -r requirements.txt   # opencv-python, numpy, requests, beautifulsoup4
python3 detect_people_webcam_advanced.py
```

Outputs `people_detection_results.json` and `people_detection_report.txt`. Default date range and daylight window (9 AM–4 PM) are hardcoded in the script.
