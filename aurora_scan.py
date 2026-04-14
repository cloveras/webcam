import cv2
import numpy as np
import multiprocessing
import os
from pathlib import Path
from datetime import datetime, timedelta

from sun_calculator import is_aurora_time

BASE_URL = "https://lilleviklofoten.no/webcam/?type=one&image="
_WORKER_EXCLUDE_ZONES = ()

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


def parse_exclude_zone(spec: str):
    """Parse x1,y1,x2,y2 in normalized full-image coordinates."""
    parts = [p.strip() for p in spec.split(",")]
    if len(parts) != 4:
        raise ValueError(f"Invalid --exclude-zone '{spec}' (expected x1,y1,x2,y2)")
    x1, y1, x2, y2 = (float(p) for p in parts)
    if not (0.0 <= x1 <= 1.0 and 0.0 <= y1 <= 1.0 and 0.0 <= x2 <= 1.0 and 0.0 <= y2 <= 1.0):
        raise ValueError(f"Invalid --exclude-zone '{spec}' (values must be 0..1)")
    if x2 <= x1 or y2 <= y1:
        raise ValueError(f"Invalid --exclude-zone '{spec}' (x2>x1 and y2>y1 required)")
    return (x1, y1, x2, y2)

def aurora_score(image_path, exclude_zones=()):
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

    # Ignore static false-positive regions if exclusion zones are configured.
    # Zone coordinates are normalized to the full input image.
    valid_mask = np.ones(H.shape, dtype=bool)
    if exclude_zones:
        h_sky, w_sky = H.shape
        for x1, y1, x2, y2 in exclude_zones:
            px1 = int(np.clip(x1, 0.0, 1.0) * w_sky)
            px2 = int(np.clip(x2, 0.0, 1.0) * w_sky)
            py1 = int(np.clip(y1 / 0.65, 0.0, 1.0) * h_sky)
            py2 = int(np.clip(y2 / 0.65, 0.0, 1.0) * h_sky)
            if px2 > px1 and py2 > py1:
                valid_mask[py1:py2, px1:px2] = False
    valid_count = int(np.count_nonzero(valid_mask))
    if valid_count == 0:
        return 0.0

    # 2) Reject globally green-tinted overcast: measure global green cast
    B, G, R = cv2.split(sky_small.astype(np.float32))
    green_cast = G - (R + B) / 2.0
    global_green_cast = float(np.mean(green_cast[valid_mask])) / 255.0  # positive means "overall green bias"

    # 3) Look for STRUCTURE: aurora tends to have local contrast/texture in V channel
    # Overcast tends to be smooth.
    blur = cv2.GaussianBlur(V, (0, 0), 3)
    contrast_map = np.abs(V.astype(np.float32) - blur.astype(np.float32))
    local_contrast = float(np.mean(contrast_map[valid_mask])) / 255.0

    # 5) Sky brightness penalty. Aurora is visible against a dark sky. Twilight
    # produces a broadly lit sky even when the sun is below the horizon. A high
    # mean V across the sky region is a strong signal for twilight, not aurora.
    # Scale factor: 1.0 for a dark sky, approaching 0 as brightness rises.
    sky_mean_v = float(np.mean(V[valid_mask])) / 255.0
    # Penalty-free up to ~0.18 (dark night). At 0.35 (twilight glow) factor ≈ 0.
    brightness_factor = max(0.0, min(1.0, (0.35 - sky_mean_v) / 0.17))

    def _component_score(h_lo, h_hi, s_min, v_min, patch_bonus=0.0):
        # 1) Candidate aurora pixels
        green = (H >= h_lo) & (H <= h_hi) & (S >= s_min) & (V >= v_min)
        green &= valid_mask
        green_ratio = float(np.count_nonzero(green)) / float(valid_count)

        # 4) Connectedness: aurora tends to form patches/bands.
        # Cap CC reward at 0.20 — a single blob covering >20% of the sky is
        # background sky (twilight gradient), not an aurora band. This prevents
        # an entire teal twilight sky from scoring extremely high.
        green_u8 = (green.astype(np.uint8) * 255)
        num_labels, labels, stats, _ = cv2.connectedComponentsWithStats(green_u8, connectivity=8)
        if num_labels <= 1:
            largest_cc_ratio = 0.0
            largest_cc_pixels = 0
        else:
            # stats[0] is background; take max area among components
            areas = stats[1:, cv2.CC_STAT_AREA]
            largest_cc_pixels = int(np.max(areas))
            largest_cc_ratio = min(float(largest_cc_pixels) / float(valid_count), 0.20)

        # Patch bonus: a compact cluster of ≥200 pixels in the target hue range
        # is a strong positive signal even when overall coverage is low.
        # Included in raw score so brightness_factor still suppresses it for
        # bright (twilight) images.
        effective_bonus = patch_bonus if largest_cc_pixels >= 200 else 0.0

        return (
            (green_ratio * 1.8) +
            (local_contrast * 1.2) +
            (largest_cc_ratio * 1.5) -
            (global_green_cast * 0.8) +
            effective_bonus
        )

    # Classic aurora green (yellow-green, H 38–85 in OpenCV 0–180 scale).
    # Patch bonus enabled: a compact cluster of yellow-green pixels can only be
    # aurora — nothing else produces that colour in a night sky.
    score_classic = _component_score(38, 85, 55, 25, patch_bonus=0.10)

    # Teal/cyan aurora (H 38–100): captures cameras that render aurora as blue-green.
    # Capped at H=100 to exclude the blue end of the spectrum (H 100–130) which
    # matches pre-dawn/post-dusk twilight sky rather than aurora. No patch bonus —
    # cyan pixels can also be polar night twilight glow or atmospheric scattering.
    score_teal = _component_score(38, 100, 55, 25, patch_bonus=0.0)

    # Apply brightness factor last so it suppresses both components equally.
    # Twilight sky (bright) is pushed toward zero; dark aurora sky is unaffected.
    return float(max(score_classic, score_teal) * brightness_factor)



def _worker_init(exclude_zones):
    global _WORKER_EXCLUDE_ZONES
    _WORKER_EXCLUDE_ZONES = tuple(exclude_zones or ())


def _score_worker(path):
    """Top-level function required for multiprocessing pickling."""
    # Suppress libjpeg "Premature end of JPEG file" warnings that come from
    # IMREAD_REDUCED_COLOR_4 decoding partial DCT data. The images are fine.
    devnull = os.open(os.devnull, os.O_WRONLY)
    old_stderr = os.dup(2)
    os.dup2(devnull, 2)
    try:
        score = aurora_score(path, exclude_zones=_WORKER_EXCLUDE_ZONES)
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

def _apply_temporal_consistency(scored, threshold, window_minutes, min_neighbors):
    """Drop isolated threshold crossings without nearby support frames."""
    if window_minutes <= 0 or min_neighbors <= 0:
        return scored

    window = timedelta(minutes=window_minutes)
    by_dt = []
    for idx, (score, path) in enumerate(scored):
        dt = parse_dt_from_stem(path.stem)
        if dt and score >= threshold:
            by_dt.append((dt, idx))
    by_dt.sort(key=lambda x: x[0])
    if not by_dt:
        return scored

    keep = set()
    for i, (dt, idx) in enumerate(by_dt):
        left = i - 1
        right = i + 1
        neighbors = 0
        while left >= 0 and (dt - by_dt[left][0]) <= window:
            neighbors += 1
            if neighbors >= min_neighbors:
                break
            left -= 1
        while neighbors < min_neighbors and right < len(by_dt) and (by_dt[right][0] - dt) <= window:
            neighbors += 1
            if neighbors >= min_neighbors:
                break
            right += 1
        if neighbors >= min_neighbors:
            keep.add(idx)

    filtered = []
    dropped = 0
    for idx, item in enumerate(scored):
        score, _ = item
        if score < threshold or idx in keep:
            filtered.append(item)
        else:
            dropped += 1
    if dropped:
        print(f"Temporal filter dropped {dropped} isolated detections")
    return filtered


def scan_folder(folder, limit=50, threshold=0.0, night_only=False, workers=None, exclude_zones=(), temporal_window_minutes=0, temporal_min_neighbors=1):
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
    above_threshold = 0
    tick = 0
    spinner = ["-", "\\", "|", "/"]

    num_workers = workers if workers is not None else multiprocessing.cpu_count()
    try:
        with multiprocessing.Pool(processes=num_workers, initializer=_worker_init, initargs=(tuple(exclude_zones),)) as pool:
            for score, path in pool.imap_unordered(_score_worker, paths, chunksize=1):
                scanned += 1
                tick += 1
                results.append((score, path))
                if score >= threshold:
                    above_threshold += 1
                print(f"\r  {spinner[tick % 4]} {scanned}/{total} scanned, {above_threshold} above threshold", end="", flush=True)
    except KeyboardInterrupt:
        print(f"\n\nInterrupted after {scanned}/{total} images.")

    print()  # newline after progress line
    results = _apply_temporal_consistency(
        results,
        threshold=threshold,
        window_minutes=temporal_window_minutes,
        min_neighbors=temporal_min_neighbors,
    )
    results = [(score, path) for score, path in results if score >= threshold]
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
    if year:
        # Whole-year scan: clear all months for this year, even if 0 results.
        return {f"{year}{m:02d}" for m in range(1, 13)}
    # Unknown layout — use months present in the results
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
    parser.add_argument("--exclude-zone", action="append", default=[], metavar="x1,y1,x2,y2", help="Ignore this normalized image zone (repeatable)")
    parser.add_argument("--temporal-window-minutes", type=int, default=0, help="Require nearby detections within this time window (0 disables)")
    parser.add_argument("--temporal-min-neighbors", type=int, default=1, help="Min nearby detections required by temporal filter")

    args = parser.parse_args()
    try:
        exclude_zones = tuple(parse_exclude_zone(z) for z in args.exclude_zone)
    except ValueError as e:
        parser.error(str(e))

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
        exclude_zones=exclude_zones,
        temporal_window_minutes=args.temporal_window_minutes,
        temporal_min_neighbors=args.temporal_min_neighbors,
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
