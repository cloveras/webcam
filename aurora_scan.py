import cv2
import numpy as np
import multiprocessing
import os
from pathlib import Path
from datetime import datetime

from sun_calculator import is_aurora_time

BASE_URL = "https://lilleviklofoten.no/webcam/?type=one&image="

def parse_dt_from_stem(stem: str):
    # Try exact match first (standard renamed files: YYYYMMDDHHMMSS)
    try:
        return datetime.strptime(stem, "%Y%m%d%H%M%S")
    except ValueError:
        pass
    # Extract 14-digit timestamp from pre-rename filenames like
    # "Lillevik Lofoten_01_20260313054407" or "Viktun_01_20260313054407"
    import re
    m = re.search(r'(\d{14})$', stem)
    if m:
        try:
            return datetime.strptime(m.group(1), "%Y%m%d%H%M%S")
        except ValueError:
            pass
    return None

def aurora_score(image_path):
    # IMREAD_REDUCED_COLOR_4 decodes the JPEG at 1/4 resolution in the decoder
    # itself — much faster than reading full-size and resizing in Python.
    img = cv2.imread(str(image_path), cv2.IMREAD_REDUCED_COLOR_4)
    if img is None:
        img = cv2.imread(str(image_path))  # fallback for non-JPEG or older OpenCV
    if img is None:
        return 0.0

    h, w, _ = img.shape

    # Ignore bottom 35% to reduce lights/sea/ground reflections
    sky = img[0:int(h*0.65), :, :]

    # Downscale for speed + smoother stats
    sky_small = cv2.resize(sky, (640, int(640 * sky.shape[0] / sky.shape[1])))

    hsv = cv2.cvtColor(sky_small, cv2.COLOR_BGR2HSV)
    H, S, V = cv2.split(hsv)

    # 2) Reject globally green-tinted overcast: measure global green cast
    B, G, R = cv2.split(sky_small.astype(np.float32))
    global_green_cast = np.mean(G - (R + B) / 2.0) / 255.0  # positive means "overall green bias"

    # 3) Look for STRUCTURE: aurora tends to have local contrast/texture in V channel
    # Overcast tends to be smooth.
    blur = cv2.GaussianBlur(V, (0, 0), 3)
    local_contrast = np.mean(np.abs(V.astype(np.float32) - blur.astype(np.float32))) / 255.0

    # 5) Sky brightness penalty. Aurora is visible against a dark sky. Twilight
    # produces a broadly lit sky even when the sun is below the horizon. A high
    # mean V across the sky region is a strong signal for twilight, not aurora.
    # Scale factor: 1.0 for a dark sky, approaching 0 as brightness rises.
    sky_mean_v = float(np.mean(V)) / 255.0
    # Penalty-free up to ~0.18 (dark night). At 0.35 (twilight glow) factor ≈ 0.
    brightness_factor = max(0.0, min(1.0, (0.35 - sky_mean_v) / 0.17))

    def _component_score(h_lo, h_hi, s_min, v_min):
        # 1) Candidate aurora pixels
        green = (H >= h_lo) & (H <= h_hi) & (S >= s_min) & (V >= v_min)
        green_ratio = green.mean()

        # 4) Connectedness: aurora tends to form patches/bands.
        # Cap CC reward at 0.20 — a single blob covering >20% of the sky is
        # background sky (twilight gradient), not an aurora band. This prevents
        # an entire teal twilight sky from scoring extremely high.
        green_u8 = (green.astype(np.uint8) * 255)
        num_labels, labels, stats, _ = cv2.connectedComponentsWithStats(green_u8, connectivity=8)
        if num_labels <= 1:
            largest_cc_ratio = 0.0
        else:
            # stats[0] is background; take max area among components
            areas = stats[1:, cv2.CC_STAT_AREA]
            largest_cc_ratio = min(float(np.max(areas)) / float(green_u8.size), 0.20)

        return (
            (green_ratio * 1.8) +
            (local_contrast * 1.2) +
            (largest_cc_ratio * 1.5) -
            (global_green_cast * 0.8)
        )

    # Classic aurora green (yellow-green, H 38–85 in OpenCV 0–180 scale).
    # S>=25, V>=15 to catch faint/diffuse green aurora that this camera renders
    # with low saturation. False positives in this hue range are rare (twilight
    # sky sits at H 100–130, well outside H 38–85).
    score_classic = _component_score(38, 85, 25, 15)

    # Teal/cyan aurora (H 38–100): captures cameras that render aurora as blue-green.
    # Capped at H=100 to exclude the blue sky range (H 100–130) which matches
    # pre-dawn/post-dusk twilight. Loose S/V thresholds are safe here because
    # twilight false positives sit above H=100.
    score_teal = _component_score(38, 100, 25, 15)

    # Apply brightness factor last so it suppresses both components equally.
    # Twilight sky (bright) is pushed toward zero; dark aurora sky is unaffected.
    return float(max(score_classic, score_teal) * brightness_factor)

def _score_worker(path):
    """Top-level function required for multiprocessing pickling."""
    # Suppress libjpeg "Premature end of JPEG file" warnings that come from
    # IMREAD_REDUCED_COLOR_4 decoding partial DCT data. The images are fine.
    devnull = os.open(os.devnull, os.O_WRONLY)
    old_stderr = os.dup(2)
    os.dup2(devnull, 2)
    try:
        score = aurora_score(path)
    finally:
        os.dup2(old_stderr, 2)
        os.close(old_stderr)
        os.close(devnull)
    return (score, path)

def human_time_from_filename(stem):
    dt = parse_dt_from_stem(stem)
    if not dt:
        return stem
    return dt.strftime("%Y-%m-%d %H:%M:%S")

def scan_folder(folder, limit=50, threshold=0.0, night_only=False, workers=None):
    # Collect paths first so we know the total count upfront.
    # Print progress during collection — can be slow on network volumes.
    print("Collecting file list...", end="", flush=True)
    paths = []
    skipped_time = 0
    for path in Path(folder).rglob("*.jpg"):
        if "mini" in str(path):
            continue
        stem = path.stem
        dt = parse_dt_from_stem(stem)
        if night_only and dt:
            if not is_aurora_time(dt):
                skipped_time += 1
                continue
        paths.append(path)
        if len(paths) % 500 == 0:
            print(f"\rCollecting file list... {len(paths)} found", end="", flush=True)

    total = len(paths)
    print(f"\rFound {total} images to scan ({skipped_time} skipped by time filter)    ")

    results = []
    scanned = 0
    tick = 0
    spinner = ["-", "\\", "|", "/"]

    num_workers = workers if workers is not None else multiprocessing.cpu_count()
    try:
        with multiprocessing.Pool(processes=num_workers) as pool:
            for score, path in pool.imap_unordered(_score_worker, paths, chunksize=1):
                scanned += 1
                tick += 1
                if score >= threshold:
                    results.append((score, path))
                print(f"\r  {spinner[tick % 4]} {scanned}/{total} scanned, {len(results)} above threshold", end="", flush=True)
    except KeyboardInterrupt:
        print(f"\n\nInterrupted after {scanned}/{total} images.")

    print()  # newline after progress line
    results.sort(reverse=True)

    print(f"\nTop {limit} likely aurora frames:\n")
    for score, path in results[:limit]:
        timestamp = path.stem
        url = BASE_URL + timestamp
        readable = human_time_from_filename(timestamp)
        print(f"{score:.4f}  {readable}")
        print(f"        {url}")

    print(f"\nScanned {scanned} images, kept {len(results)} above threshold {threshold}")
    return results

def _infer_scanned_months(folder, new_data):
    """
    Return the set of YYYYMM strings that were covered by this scan.

    If the folder path contains a specific month (e.g. .../2026/03) or day
    (.../2026/03/15), derive the month directly from the path so that a scan
    returning 0 results still removes old entries for that month.
    Fall back to the months present in new_data when the path is ambiguous
    (e.g. a whole-year scan).
    """
    parts = Path(folder).parts
    year = None
    month = None
    for i, part in enumerate(parts):
        if part.isdigit() and len(part) == 4 and 2000 <= int(part) <= 2100:
            year = part
            if i + 1 < len(parts) and parts[i + 1].isdigit() and len(parts[i + 1]) == 2:
                month = parts[i + 1]
            break
    if year and month:
        return {year + month}
    # Whole-year scan or unknown layout — use months from the results
    return {x["timestamp"][:6] for x in new_data}


def _infer_year(folder):
    """Extract a 4-digit year from the folder path, e.g. /images/2026 or /images/2026/03."""
    for part in Path(folder).parts:
        if part.isdigit() and len(part) == 4 and 2000 <= int(part) <= 2100:
            return part
    return None


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(
        description="Scan webcam images for aurora likelihood.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    parser.add_argument("folder", help="Folder to scan (year, month, or day directory)")
    parser.add_argument("--limit", type=int, default=50, help="Number of results to print")
    parser.add_argument("--threshold", type=float, default=0.15, help="Minimum score to include")
    parser.add_argument("--day", action="store_true", help="Scan all images including daytime (default: night only)")
    parser.add_argument("--workers", type=int, default=None, help="Parallel workers (default: all CPU cores; try 1-2 for network drives)")
    parser.add_argument("--json-output", metavar="FILE", help="JSON output file (default: data/aurora-YYYY.json derived from folder path)")
    parser.add_argument("--append", action="store_true", help="Upsert entries by timestamp instead of replacing the whole scanned month")

    args = parser.parse_args()

    # Derive json output path from folder year if not given explicitly
    json_output = args.json_output
    if json_output is None:
        year = _infer_year(args.folder)
        if year:
            json_output = f"data/aurora-{year}.json"

    results = scan_folder(
        args.folder,
        limit=args.limit,
        threshold=args.threshold,
        night_only=not args.day,
        workers=args.workers,
    )

    if json_output:
        import json
        def _ts(path):
            dt = parse_dt_from_stem(path.stem)
            return dt.strftime("%Y%m%d%H%M%S") if dt else path.stem
        new_data = sorted(
            [{"timestamp": _ts(path), "score": round(score, 4)} for score, path in results],
            key=lambda x: x["timestamp"]
        )
        output_path = Path(json_output)
        if output_path.exists():
            existing = json.loads(output_path.read_text())
            if args.append:
                # Upsert mode: merge new entries into existing by timestamp
                if not new_data:
                    print(f"\nNo new results; {json_output} unchanged.")
                else:
                    by_ts = {x["timestamp"]: x for x in existing}
                    for item in new_data:
                        by_ts[item["timestamp"]] = item
                    merged = sorted(by_ts.values(), key=lambda x: x["timestamp"])
                    output_path.write_text(json.dumps(merged, indent=2))
                    print(f"\nJSON updated in {json_output} ({len(merged)} total entries, {len(new_data)} new/updated)")
            else:
                # Replace mode: remove all entries for scanned months, add new ones.
                # This correctly clears false positives when a rescan finds 0 results.
                scanned_months = _infer_scanned_months(args.folder, new_data)
                kept = [x for x in existing if x["timestamp"][:6] not in scanned_months]
                merged = sorted(kept + new_data, key=lambda x: x["timestamp"])
                output_path.write_text(json.dumps(merged, indent=2))
                removed = len(existing) - len(kept)
                print(f"\nJSON merged into {json_output} ({len(merged)} total entries, {len(new_data)} from this scan, {removed} removed)")
        else:
            output_path.write_text(json.dumps(new_data, indent=2))
            print(f"\nJSON written to {json_output} ({len(new_data)} entries)")
