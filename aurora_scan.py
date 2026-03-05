import cv2
import numpy as np
import multiprocessing
from pathlib import Path
from datetime import datetime

from sun_calculator import is_aurora_time

BASE_URL = "https://lilleviklofoten.no/webcam/?type=one&image="

def parse_dt_from_stem(stem: str):
    try:
        return datetime.strptime(stem, "%Y%m%d%H%M%S")
    except:
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

    # 1) Candidate "aurora green" pixels: green-ish hue + decent saturation + not too dark
    green = (H >= 38) & (H <= 85) & (S >= 55) & (V >= 25)

    green_ratio = green.mean()

    # 2) Reject globally green-tinted overcast: measure global green cast
    B, G, R = cv2.split(sky_small.astype(np.float32))
    global_green_cast = np.mean(G - (R + B) / 2.0) / 255.0  # positive means "overall green bias"

    # 3) Look for STRUCTURE: aurora tends to have local contrast/texture in V channel
    # Overcast tends to be smooth.
    blur = cv2.GaussianBlur(V, (0, 0), 3)
    local_contrast = np.mean(np.abs(V.astype(np.float32) - blur.astype(np.float32))) / 255.0

    # 4) Connectedness: aurora tends to form patches/bands.
    # Compute largest connected green component size ratio.
    green_u8 = (green.astype(np.uint8) * 255)
    num_labels, labels, stats, _ = cv2.connectedComponentsWithStats(green_u8, connectivity=8)
    if num_labels <= 1:
        largest_cc_ratio = 0.0
    else:
        # stats[0] is background; take max area among components
        areas = stats[1:, cv2.CC_STAT_AREA]
        largest_cc_ratio = float(np.max(areas)) / float(green_u8.size)

    # Combine:
    # - green_ratio is necessary but not sufficient
    # - local_contrast + largest_cc_ratio promotes structured aurora
    # - global_green_cast penalizes "uniform green fog"
    score = (
        (green_ratio * 1.8) +
        (local_contrast * 1.2) +
        (largest_cc_ratio * 1.5) -
        (global_green_cast * 0.8)
    )

    return float(score)

def _score_worker(path):
    """Top-level function required for multiprocessing pickling."""
    return (aurora_score(path), path)

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
    spinner = ["-", "\\", "|", "/"]

    num_workers = workers if workers is not None else multiprocessing.cpu_count()
    try:
        with multiprocessing.Pool(processes=num_workers) as pool:
            for score, path in pool.imap_unordered(_score_worker, paths, chunksize=4):
                scanned += 1
                if score >= threshold:
                    results.append((score, path))
                spin = spinner[scanned % 4]
                    print(f"\r  {spin} {scanned}/{total} scanned, {len(results)} above threshold", end="", flush=True)
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

if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Scan webcam images for aurora likelihood.")
    parser.add_argument("folder", help="Folder to scan")
    parser.add_argument("--limit", type=int, default=50, help="Number of results to show")
    parser.add_argument("--threshold", type=float, default=0.0, help="Minimum score to include")
    parser.add_argument("--night", action="store_true", help="Only scan images taken during astronomical darkness (before nautical dawn / after nautical dusk, accounting for midnight sun and polar night)")
    parser.add_argument("--workers", type=int, default=None, help="Number of parallel workers (default: all CPU cores; try 1-2 for network drives)")
    parser.add_argument("--json-output", metavar="FILE", help="Write results as JSON to FILE (sorted by timestamp, all results above threshold)")

    args = parser.parse_args()

    results = scan_folder(
        args.folder,
        limit=args.limit,
        threshold=args.threshold,
        night_only=args.night,
        workers=args.workers,
    )

    if args.json_output:
        import json
        new_data = sorted(
            [{"timestamp": path.stem, "score": round(score, 4)} for score, path in results],
            key=lambda x: x["timestamp"]
        )
        output_path = Path(args.json_output)
        if output_path.exists() and new_data:
            existing = json.loads(output_path.read_text())
            scanned_months = {x["timestamp"][:6] for x in new_data}
            kept = [x for x in existing if x["timestamp"][:6] not in scanned_months]
            merged = sorted(kept + new_data, key=lambda x: x["timestamp"])
            output_path.write_text(json.dumps(merged, indent=2))
            print(f"\nJSON merged into {args.json_output} ({len(merged)} total entries, {len(new_data)} from this scan)")
        else:
            output_path.write_text(json.dumps(new_data, indent=2))
            print(f"\nJSON written to {args.json_output} ({len(new_data)} entries)")
