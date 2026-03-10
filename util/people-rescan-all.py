#!/usr/bin/env python3
"""
people-rescan-all.py — rebuild per-month background models and re-scan people/animal/vehicle
detections for all years, most recent year first.

Per-month backgrounds account for seasonal variation (snow, grass, hay bales),
slight camera position drift, and the large daylight difference between winter and summer.
Existing background files are reused unless --rebuild-backgrounds is given.

Handles the Lillevik camera change on 2025-07-26:
  • Before: 2560×1920 (4:3)
  • After:  3840×2160 (4K 16:9)
July 2025 is scanned in two passes with separate backgrounds sourced from June 2025
(old camera) and August 2025 (new camera).

Usage:
    # Full run — all years, most recent first (run overnight)
    python3 util/people-rescan-all.py

    # One or more specific years (skips background rebuild by default)
    python3 util/people-rescan-all.py 2026
    python3 util/people-rescan-all.py 2025 2026

    # Force rebuild of all background models
    python3 util/people-rescan-all.py --rebuild-backgrounds
    python3 util/people-rescan-all.py --rebuild-backgrounds 2026
"""

import argparse
import subprocess
import sys
from datetime import datetime
from pathlib import Path

# ── Configuration ─────────────────────────────────────────────────────────────

WEBCAM_DIR = Path.home() / "Dev/webcam"
LILLEVIK   = Path("/Volumes/homes/cl/Lillevik-webcam")
VIKTUN     = Path("/Volumes/homes/cl/Viktun-webcam")
PYTHON     = str(WEBCAM_DIR / "venv/bin/python3")
SCANNER    = str(WEBCAM_DIR / "people_scan.py")

# Lillevik camera change: first day of new 4K (16:9) camera
CHANGE_DATE = "20250726"   # YYYYMMDD
CHANGE_YEAR = 2025
CHANGE_MONTH = 7

THRESHOLD  = "0.2"
WORKERS    = "4"
BG_SAMPLES = "100"   # per-month background: 100 frames is fast and accurate enough

# Old camera: 2560×1920 (4:3), used up to and including 2025-07-25.
# Zones calibrated for the 4:3 framing.
LILLEVIK_ZONES_OLD = [
    "--exclude-zone", "0.0,0.0,1.0,0.68",   # sky / mountains / water (top 68%)
    "--exclude-zone", "0.52,0.70,0.61,0.81", # boathouse
    "--exclude-zone", "0.40,0.88,0.46,0.99", # foreground poles
]

# New camera: 3840×2160 (4K 16:9), used from 2025-07-26 onwards.
# Two-zone sky exclusion to follow the non-flat shore boundary:
#   Zone 1: excludes top 60% across the full width (sky, mountains, open water).
#   Zone 2: additionally excludes the left-side shore/water (x < 45%, y 60–68%),
#            where the land in the left field is lower in the frame than on the right.
# Together the effective exclusion boundary runs from y≈0.68 on the left to y≈0.60
# on the right, closely matching the actual waterline shape.
LILLEVIK_ZONES_NEW = [
    "--exclude-zone", "0.0,0.0,1.0,0.60",   # sky / mountains / main water (top 60%)
    "--exclude-zone", "0.0,0.60,0.45,0.68", # left-side shore / water (x < 45%, y 60–68%)
    "--exclude-zone", "0.52,0.70,0.61,0.81", # boathouse
    "--exclude-zone", "0.40,0.88,0.46,0.99", # foreground poles
]

VIKTUN_ZONES = [
    "--exclude-zone", "0.0,0.0,1.0,0.50",   # sky (top 50%)
    "--exclude-zone", "0.0,0.45,0.30,0.68", # mountain on left
]

# For the 2025 July split, fall back to a nearby month for the background source.
# Old camera (Jul 1-25): source from June or May 2025 (same camera, similar season).
# New camera (Jul 26-31): source from August or September 2025.
OLD_CAMERA_JULY_BG_SOURCES = ["2025/06", "2025/05", "2025/04"]
NEW_CAMERA_JULY_BG_SOURCES = ["2025/08", "2025/09", "2026/01"]

# ── Helpers ───────────────────────────────────────────────────────────────────

def log(msg: str):
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"\n{'='*60}\n{ts}  {msg}\n{'='*60}", flush=True)


def run(cmd: list[str]):
    print("$", " ".join(str(c) for c in cmd), flush=True)
    try:
        result = subprocess.run(cmd)
    except KeyboardInterrupt:
        print("\nInterrupted.", flush=True)
        sys.exit(1)
    if result.returncode != 0:
        print(f"ERROR: exit code {result.returncode}", flush=True)
        sys.exit(result.returncode)


def available_years(base_dir: Path) -> list[int]:
    return sorted(
        int(p.name) for p in base_dir.iterdir()
        if p.is_dir() and p.name.isdigit() and len(p.name) == 4
    )


def available_months(year_dir: Path) -> list[int]:
    return sorted(
        int(p.name) for p in year_dir.iterdir()
        if p.is_dir() and p.name.isdigit() and len(p.name) == 2
    )


def find_source_month(base_dir: Path, candidates: list[str]) -> Path | None:
    """Return the first year/month path from candidates that exists under base_dir."""
    for rel in candidates:
        p = base_dir / rel
        if p.is_dir():
            return p
    return None


def build_background(source_dir: Path, output: Path):
    log(f"Building background  {source_dir.relative_to(LILLEVIK.parent)}  →  {output.name}")
    output.parent.mkdir(parents=True, exist_ok=True)
    run([PYTHON, SCANNER, str(source_dir),
         "--build-background", str(output),
         "--bg-samples", BG_SAMPLES])


def ensure_background(source_dir: Path, output: Path, rebuild: bool):
    """Build background if it doesn't exist or rebuild is requested."""
    if output.exists() and not rebuild:
        print(f"  (reusing existing {output.name})", flush=True)
        return True
    if not source_dir.is_dir():
        print(f"  WARNING: background source not found: {source_dir}", flush=True)
        return False
    build_background(source_dir, output)
    return output.exists()


# ── Lillevik scanning ─────────────────────────────────────────────────────────

def scan_lillevik(years: list[int], rebuild_bg: bool):
    bg_dir = WEBCAM_DIR / "data"
    bg_dir.mkdir(parents=True, exist_ok=True)

    for year in sorted(years, reverse=True):
        year_dir = LILLEVIK / str(year)
        if not year_dir.is_dir():
            print(f"Lillevik {year}: not found, skipping", flush=True)
            continue

        json_out = WEBCAM_DIR / f"data/people-{year}.json"
        months = available_months(year_dir)

        for month in sorted(months, reverse=True):
            month_dir = year_dir / f"{month:02d}"

            # ── 2025 July: two-pass split around camera change ────────────────
            if year == CHANGE_YEAR and month == CHANGE_MONTH:
                # Pass 1: old camera, Jul 1–25
                old_bg = bg_dir / "background-2025-07-old.png"
                src = find_source_month(LILLEVIK, OLD_CAMERA_JULY_BG_SOURCES)
                if src and ensure_background(src, old_bg, rebuild_bg):
                    log(f"Scanning Lillevik {year}-{month:02d} — old camera (before {CHANGE_DATE})")
                    run([PYTHON, SCANNER, str(month_dir),
                         "--before", CHANGE_DATE,
                         "--civil-day", "--threshold", THRESHOLD, "--workers", WORKERS,
                         "--background", str(old_bg),
                         *LILLEVIK_ZONES_OLD,
                         "--json-output", str(json_out)])

                # Pass 2: new camera, Jul 26–31 (append — don't overwrite pass 1)
                new_bg = bg_dir / "background-2025-07-new.png"
                src = find_source_month(LILLEVIK, NEW_CAMERA_JULY_BG_SOURCES)
                if src and ensure_background(src, new_bg, rebuild_bg):
                    log(f"Scanning Lillevik {year}-{month:02d} — new camera (from {CHANGE_DATE})")
                    run([PYTHON, SCANNER, str(month_dir),
                         "--after", CHANGE_DATE,
                         "--civil-day", "--threshold", THRESHOLD, "--workers", WORKERS,
                         "--background", str(new_bg),
                         *LILLEVIK_ZONES_NEW,
                         "--json-output", str(json_out),
                         "--append"])
                continue

            # ── Regular month ─────────────────────────────────────────────────
            zones = LILLEVIK_ZONES_NEW if year > CHANGE_YEAR or (year == CHANGE_YEAR and month > CHANGE_MONTH) else LILLEVIK_ZONES_OLD
            bg_file = bg_dir / f"background-{year}-{month:02d}.png"
            if ensure_background(month_dir, bg_file, rebuild_bg):
                log(f"Scanning Lillevik {year}-{month:02d}")
                run([PYTHON, SCANNER, str(month_dir),
                     "--civil-day", "--threshold", THRESHOLD, "--workers", WORKERS,
                     "--background", str(bg_file),
                     *zones,
                     "--json-output", str(json_out)])


# ── Viktun scanning ───────────────────────────────────────────────────────────

def scan_viktun(years: list[int], rebuild_bg: bool):
    if not VIKTUN.is_dir():
        return

    bg_dir = WEBCAM_DIR / "viktun/data"
    bg_dir.mkdir(parents=True, exist_ok=True)

    for year in sorted(years, reverse=True):
        year_dir = VIKTUN / str(year)
        if not year_dir.is_dir():
            continue

        json_out = WEBCAM_DIR / f"viktun/data/people-{year}.json"
        months = available_months(year_dir)

        for month in sorted(months, reverse=True):
            month_dir = year_dir / f"{month:02d}"
            bg_file = bg_dir / f"background-{year}-{month:02d}.png"

            if ensure_background(month_dir, bg_file, rebuild_bg):
                log(f"Scanning Viktun {year}-{month:02d}")
                run([PYTHON, SCANNER, str(month_dir),
                     "--civil-day", "--threshold", THRESHOLD, "--workers", WORKERS,
                     "--background", str(bg_file),
                     *VIKTUN_ZONES,
                     "--json-output", str(json_out)])


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description=__doc__,
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument("years", nargs="*", type=int,
                        help="Years to process (default: all available, most recent first)")
    parser.add_argument("--rebuild-backgrounds", action="store_true",
                        help="Rebuild all background models even if they already exist")
    args = parser.parse_args()

    if not LILLEVIK.is_dir():
        print(f"ERROR: {LILLEVIK} not found. Is the NAS mounted?", flush=True)
        sys.exit(1)

    lillevik_years = args.years if args.years else available_years(LILLEVIK)
    viktun_years   = [y for y in (args.years or available_years(VIKTUN))
                      if VIKTUN.is_dir() and (VIKTUN / str(y)).is_dir()]

    print(f"Lillevik years: {sorted(lillevik_years, reverse=True)}", flush=True)
    print(f"Viktun years:   {sorted(viktun_years,   reverse=True)}", flush=True)
    print(f"Rebuild backgrounds: {args.rebuild_backgrounds}", flush=True)

    scan_lillevik(lillevik_years, args.rebuild_backgrounds)

    if viktun_years:
        scan_viktun(viktun_years, args.rebuild_backgrounds)

    log("All done. Upload JSON files to the server:")
    print(f"  rsync -az -e 'ssh -p 22' {WEBCAM_DIR}/data/ "
          f"lilleviklofoten@login.domeneshop.no:www/webcam/data/")
    print(f"  rsync -az -e 'ssh -p 22' {WEBCAM_DIR}/viktun/data/ "
          f"lilleviklofoten@login.domeneshop.no:www/webcam/viktun/data/")


if __name__ == "__main__":
    main()
