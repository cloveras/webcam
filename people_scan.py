"""
people_scan.py — scan webcam images for people using YOLOv8.

Mirrors aurora_scan.py: same CLI, same JSON merge/replace behaviour.
Score = highest person-detection confidence in the frame (0–1).

Usage:
    # Update a single month (fast)
    python3 people_scan.py /path/to/images/2026/02 --day --threshold 0.3 --json-output data/people-2026.json

    # Full year scan (slow, use for initial build)
    python3 people_scan.py /path/to/images/2026 --day --threshold 0.3 --json-output data/people-2026.json

Dependencies: ultralytics, astral  (pip install ultralytics astral)
"""

import json
import multiprocessing
import os
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

from sun_calculator import find_sun_times

BASE_URL = "https://lilleviklofoten.no/webcam/?type=one&image="
_TZ = ZoneInfo("Europe/Oslo")


def parse_dt_from_stem(stem: str):
    try:
        return datetime.strptime(stem, "%Y%m%d%H%M%S")
    except Exception:
        return None


def is_daytime(dt: datetime) -> bool:
    """Return True if dt falls between dawn and dusk (handles midnight sun and polar night)."""
    dawn, dusk, _ms, _pn = find_sun_times(dt.date())
    aware = dt.replace(tzinfo=_TZ) if dt.tzinfo is None else dt
    return dawn <= aware <= dusk


# ── Per-worker model (loaded once per worker process) ─────────────────────────

_worker_model = None


def _get_model():
    global _worker_model
    if _worker_model is None:
        import logging
        logging.getLogger("ultralytics").setLevel(logging.WARNING)
        from ultralytics import YOLO
        _worker_model = YOLO("yolov8n.pt")
    return _worker_model


def people_score(image_path) -> float:
    """Return highest person-detection confidence in the image, or 0.0 if none found."""
    model = _get_model()
    results = model(str(image_path), verbose=False, device="cpu")
    best = 0.0
    for box in results[0].boxes:
        if int(box.cls) == 0:  # class 0 = person
            conf = float(box.conf)
            if conf > best:
                best = conf
    return best


def _score_worker(path):
    """Top-level function required for multiprocessing pickling."""
    # Suppress stderr noise from image decoders
    devnull = os.open(os.devnull, os.O_WRONLY)
    old_stderr = os.dup(2)
    os.dup2(devnull, 2)
    try:
        score = people_score(path)
    finally:
        os.dup2(old_stderr, 2)
        os.close(old_stderr)
        os.close(devnull)
    return (score, path)


# ── Main scan ─────────────────────────────────────────────────────────────────

def scan_folder(folder, threshold=0.0, limit=50, day_only=False, workers=None):
    print("Collecting file list...", end="", flush=True)
    paths = []
    skipped_time = 0
    for path in Path(folder).rglob("*.jpg"):
        if "mini" in str(path):
            continue
        stem = path.stem
        dt = parse_dt_from_stem(stem)
        if day_only and dt:
            if not is_daytime(dt):
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

    print()
    results.sort(reverse=True)

    print(f"\nTop {limit} likely frames with people:\n")
    for score, path in results[:limit]:
        timestamp = path.stem
        url = BASE_URL + timestamp
        dt = parse_dt_from_stem(timestamp)
        readable = dt.strftime("%Y-%m-%d %H:%M:%S") if dt else timestamp
        print(f"{score:.4f}  {readable}")
        print(f"        {url}")

    print(f"\nScanned {scanned} images, kept {len(results)} above threshold {threshold}")
    return results


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Scan webcam images for people using YOLOv8.")
    parser.add_argument("folder", help="Folder to scan")
    parser.add_argument("--threshold", type=float, default=0.0,
                        help="Minimum detection confidence to include (0–1, default 0)")
    parser.add_argument("--limit", type=int, default=50,
                        help="Cap the stdout report at N results (does not affect JSON output)")
    parser.add_argument("--day", action="store_true",
                        help="Only scan images taken during daylight (dawn to dusk, handles midnight sun and polar night)")
    parser.add_argument("--workers", type=int, default=None,
                        help="Number of parallel workers (default: all CPU cores; try 1–2 for network drives)")
    parser.add_argument("--json-output", metavar="FILE",
                        help="Write results as JSON to FILE (sorted by timestamp, all results above threshold)")
    parser.add_argument("--append", action="store_true",
                        help="Upsert entries by timestamp instead of replacing the whole scanned month")

    args = parser.parse_args()

    results = scan_folder(
        args.folder,
        threshold=args.threshold,
        limit=args.limit,
        day_only=args.day,
        workers=args.workers,
    )

    if args.json_output:
        new_data = sorted(
            [{"timestamp": path.stem, "score": round(score, 4)} for score, path in results],
            key=lambda x: x["timestamp"]
        )
        output_path = Path(args.json_output)
        if output_path.exists() and new_data:
            existing = json.loads(output_path.read_text())
            if args.append:
                by_ts = {x["timestamp"]: x for x in existing}
                for item in new_data:
                    by_ts[item["timestamp"]] = item
                merged = sorted(by_ts.values(), key=lambda x: x["timestamp"])
                output_path.write_text(json.dumps(merged, indent=2))
                print(f"\nJSON updated in {args.json_output} ({len(merged)} total entries, {len(new_data)} new/updated)")
            else:
                scanned_months = {x["timestamp"][:6] for x in new_data}
                kept = [x for x in existing if x["timestamp"][:6] not in scanned_months]
                merged = sorted(kept + new_data, key=lambda x: x["timestamp"])
                output_path.write_text(json.dumps(merged, indent=2))
                print(f"\nJSON merged into {args.json_output} ({len(merged)} total entries, {len(new_data)} from this scan)")
        else:
            output_path.write_text(json.dumps(new_data, indent=2))
            print(f"\nJSON written to {args.json_output} ({len(new_data)} entries)")
