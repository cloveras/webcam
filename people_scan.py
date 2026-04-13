"""
people_scan.py — scan webcam images for people, animals, and vehicles using YOLOv8.

Mirrors aurora_scan.py: same CLI, same JSON merge/replace behaviour.
Score = highest detection confidence in the frame (0–1).

Two complementary false-positive reduction techniques are available:

  --exclude-zone   Ignore any detection whose bounding-box centre falls
                   inside a static region (given as x1,y1,x2,y2 fractions
                   0–1).  Repeatable.  Recommended zones for this camera:

  New camera (3840×2160, 16:9, 2025-07-26+) — two-zone sky exclusion to follow
  the non-flat shoreline (left field sits lower in the frame than the right shore):
                     0.0,0.0,1.0,0.60    sky / mountains / main water (full width)
                     0.0,0.60,0.45,0.68  left-side shore / water (x < 45%, y 60–68%)
                     0.52,0.70,0.61,0.81 boathouse
                     0.40,0.88,0.46,0.99 foreground poles

  Old camera (2560×1920, 4:3, before 2025-07-26):
                     0.0,0.0,1.0,0.68    sky / mountains / water
                     0.52,0.70,0.61,0.81 boathouse
                     0.40,0.88,0.46,0.99 foreground poles

  --background     Median background image built from many empty frames.
                   A detection is only accepted when a sufficient fraction
                   of its bounding box overlaps with pixels that differ
                   from the background (i.e. something has changed).
                   Build once with --build-background, reuse thereafter.

Usage:
    # 1. Build background model once (uses all JPEGs in the folder tree)
    python3 people_scan.py /path/to/images/2026 \\
        --build-background data/background-2026.png

    # 2. Scan with all filters
    python3 people_scan.py /path/to/images/2026 --civil-day --threshold 0.3 \\
        --background data/background-2026.png \\
        --exclude-zone 0.0,0.0,1.0,0.60 \
        --exclude-zone 0.0,0.60,0.45,0.68 \\
        --exclude-zone 0.52,0.70,0.61,0.81 \\
        --exclude-zone 0.40,0.88,0.46,0.99 \\
        --json-output data/people-2026.json

Dependencies: ultralytics, astral  (pip install ultralytics astral)
"""

import json
import multiprocessing
import os
import random
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

import cv2
import numpy as np

from sun_calculator import find_sun_times
from ultralytics import YOLO

BASE_URL = "https://lilleviklofoten.no/webcam/?type=one&image="
_TZ = ZoneInfo("Europe/Oslo")

# COCO classes to detect (people, common vehicles, animals).
# Birds (14) are excluded to avoid seagull false positives.
_DETECT_CLASSES = {
    0,   # person
    1,   # bicycle
    2,   # car
    3,   # motorcycle
    5,   # bus
    7,   # truck
    8,   # boat
    15,  # cat
    16,  # dog
    17,  # horse
    18,  # sheep
    19,  # cow
}


# ── Helpers ────────────────────────────────────────────────────────────────────

def parse_dt_from_stem(stem: str):
    try:
        return datetime.strptime(stem, "%Y%m%d%H%M%S")
    except Exception:
        return None


def is_daytime(dt: datetime, depression: float = 12) -> bool:
    """Return True if dt falls between dawn and dusk (handles midnight sun and polar night)."""
    dawn, dusk, _ms, _pn = find_sun_times(dt.date(), depression=depression)
    aware = dt.replace(tzinfo=_TZ) if dt.tzinfo is None else dt
    return dawn <= aware <= dusk


# ── Per-worker state ───────────────────────────────────────────────────────────
# NOTE: multiprocessing on macOS uses "spawn", so module-level globals in the
# main process are NOT inherited by workers.  All per-worker state is set via
# _worker_init(), which is called once per worker at Pool creation time.

_worker_model = None
_background = None          # BGR uint8 ndarray, or None
_exclude_zones = []         # [(x1, y1, x2, y2), ...] as image fractions 0–1
_bg_diff_threshold = 25     # pixel intensity diff to mark a pixel as "changed"
_fg_overlap_min = 0.15      # min fraction of bbox in foreground to accept a detection
_crop_top = 0.0             # fraction of image height to crop from the top before inference


def _remap_zones(zones, crop_top):
    """Remap exclusion zones from full-image fractions to post-crop fractions.
    Zones entirely above the crop line are dropped; partial zones are clamped."""
    if not crop_top:
        return list(zones)
    scale = 1.0 - crop_top
    remapped = []
    for x1, y1, x2, y2 in zones:
        if y1 >= 1.0 or y2 <= crop_top:
            continue  # entirely above crop line — drop
        new_y1 = max(0.0, (y1 - crop_top) / scale)
        new_y2 = min(1.0, (y2 - crop_top) / scale)
        remapped.append((x1, new_y1, x2, new_y2))
    return remapped


def _worker_init(background_path, exclude_zones, fg_overlap_min, bg_diff_threshold, crop_top=0.0):
    """Called once per worker process at Pool creation. Sets all per-worker globals."""
    global _background, _exclude_zones, _fg_overlap_min, _bg_diff_threshold, _crop_top
    _crop_top = crop_top
    _exclude_zones = _remap_zones(exclude_zones, crop_top)
    _fg_overlap_min = fg_overlap_min
    _bg_diff_threshold = bg_diff_threshold
    if background_path is not None:
        bg = cv2.imread(str(background_path))
        if bg is not None:
            _background = bg


def _get_model():
    global _worker_model
    if _worker_model is None:
        _worker_model = YOLO("yolov8s.pt")
    return _worker_model


# ── Scoring ────────────────────────────────────────────────────────────────────

def _in_excluded_zone(cx: float, cy: float) -> bool:
    for x1, y1, x2, y2 in _exclude_zones:
        if x1 <= cx <= x2 and y1 <= cy <= y2:
            return True
    return False


def people_score(image_path) -> float:
    """Return highest detection confidence (people/animals/vehicles) not filtered by exclusion rules, or 0.0."""
    model = _get_model()
    raw = Path(image_path).read_bytes()
    img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        return 0.0
    h, w = img.shape[:2]

    if _crop_top > 0.0:
        img = img[int(h * _crop_top):, :]
        h = img.shape[0]

    # Build foreground mask: pixels that differ significantly from the background.
    fg_mask = None
    if _background is not None:
        bg = _background
        if bg.shape[:2] != (h, w):
            bg = cv2.resize(bg, (w, h))
        diff = cv2.absdiff(img, bg)
        gray = cv2.cvtColor(diff, cv2.COLOR_BGR2GRAY)
        _, fg_mask = cv2.threshold(gray, _bg_diff_threshold, 255, cv2.THRESH_BINARY)
        # Dilate so the full silhouette of a person is covered, not just edges.
        fg_mask = cv2.dilate(fg_mask, np.ones((20, 20), np.uint8))

    results = model(img, verbose=False, device="cpu", imgsz=1280)
    best = 0.0
    for box in results[0].boxes:
        if int(box.cls) not in _DETECT_CLASSES:
            continue

        x1, y1, x2, y2 = [int(v) for v in box.xyxy[0].tolist()]
        cx, cy = (x1 + x2) / 2 / w, (y1 + y2) / 2 / h

        # Exclusion zones: drop detections centred in known-static regions.
        if _exclude_zones and _in_excluded_zone(cx, cy):
            continue

        # Background subtraction: drop detections that match the static background.
        if fg_mask is not None:
            bx1, by1 = max(0, x1), max(0, y1)
            bx2, by2 = min(w, x2), min(h, y2)
            box_region = fg_mask[by1:by2, bx1:bx2]
            if box_region.size == 0:
                continue
            if np.count_nonzero(box_region) / box_region.size < _fg_overlap_min:
                continue

        conf = float(box.conf)
        if conf > best:
            best = conf
    return best


def _score_worker(path):
    """Top-level function required for multiprocessing pickling."""
    devnull = os.open(os.devnull, os.O_WRONLY)
    old_stderr = os.dup(2)
    os.dup2(devnull, 2)
    try:
        score = people_score(path)
    except Exception:
        score = 0.0
    finally:
        os.dup2(old_stderr, 2)
        os.close(old_stderr)
        os.close(devnull)
    return (score, path)


# ── Diagnostic visualiser ─────────────────────────────────────────────────────

def annotate_image(image_path, output_path, exclude_zones=None,
                   background_path=None, fg_overlap=0.15, bg_diff_threshold=25,
                   crop_top=0.0):
    """
    Run detection on a single image and save an annotated version showing:
      - Every YOLO detection box (green = kept, red = excluded by zone,
        orange = rejected by background check)
      - Exclusion zones as coloured semi-transparent overlays
      - Foreground mask as a faint green overlay (if background supplied)
    Use this to calibrate --exclude-zone coordinates.
    """
    raw = Path(image_path).read_bytes()
    img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        print(f"Could not load: {image_path}")
        return
    h_full, w = img.shape[:2]

    # Draw crop line on the full image before cropping
    out = img.copy()
    if crop_top > 0.0:
        crop_y_px = int(h_full * crop_top)
        cv2.line(out, (0, crop_y_px), (w, crop_y_px), (0, 255, 0), 3)
        img = img[crop_y_px:, :]
    h, w = img.shape[:2]

    zones = _remap_zones(exclude_zones or [], crop_top)
    crop_offset_px = int(h_full * crop_top)
    zone_colours = [(0, 128, 255), (255, 128, 0), (128, 0, 255), (0, 255, 128)]

    # Draw exclusion zones (offset into full-image coordinates)
    for i, (zx1, zy1, zx2, zy2) in enumerate(zones):
        px1, py1 = int(zx1 * w), crop_offset_px + int(zy1 * h)
        px2, py2 = int(zx2 * w), crop_offset_px + int(zy2 * h)
        colour = zone_colours[i % len(zone_colours)]
        overlay = out.copy()
        cv2.rectangle(overlay, (px1, py1), (px2, py2), colour, -1)
        cv2.addWeighted(overlay, 0.2, out, 0.8, 0, out)
        cv2.rectangle(out, (px1, py1), (px2, py2), colour, 2)
        cv2.putText(out, f"zone {i + 1}", (px1 + 4, py1 + 20),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, colour, 2)

    # Build foreground mask on cropped image
    fg_mask = None
    if background_path:
        bg = cv2.imread(str(background_path))
        if bg is not None:
            if bg.shape[:2] != (h_full, w):
                bg = cv2.resize(bg, (w, h_full))
            bg_crop = bg[crop_offset_px:, :]
            diff = cv2.absdiff(img, bg_crop)
            gray = cv2.cvtColor(diff, cv2.COLOR_BGR2GRAY)
            _, fg_mask = cv2.threshold(gray, bg_diff_threshold, 255, cv2.THRESH_BINARY)
            fg_mask = cv2.dilate(fg_mask, np.ones((20, 20), np.uint8))
            green_overlay = np.zeros_like(out)
            green_overlay[crop_offset_px:, :, 1] = fg_mask
            cv2.addWeighted(green_overlay, 0.15, out, 1.0, 0, out)

    # Run YOLO on cropped image
    model = YOLO("yolov8s.pt")
    results = model(img, verbose=False, device="cpu", imgsz=1280)

    class_names = results[0].names
    print(f"\nDetections in {Path(image_path).name}:")
    for box in results[0].boxes:
        if int(box.cls) not in _DETECT_CLASSES:
            continue
        x1, y1, x2, y2 = [int(v) for v in box.xyxy[0].tolist()]
        cx, cy = (x1 + x2) / 2 / w, (y1 + y2) / 2 / h
        conf = float(box.conf)

        reason = None
        colour = (0, 200, 0)  # green = kept

        for zx1, zy1, zx2, zy2 in zones:
            if zx1 <= cx <= zx2 and zy1 <= cy <= zy2:
                reason = "excl.zone"
                colour = (0, 0, 220)  # red
                break

        if reason is None and fg_mask is not None:
            bx1, by1 = max(0, x1), max(0, y1)
            bx2, by2 = min(w, x2), min(h, y2)
            box_region = fg_mask[by1:by2, bx1:bx2]
            if box_region.size > 0:
                frac = np.count_nonzero(box_region) / box_region.size
                if frac < fg_overlap:
                    reason = f"bg({frac:.2f}<{fg_overlap})"
                    colour = (0, 140, 255)  # orange

        # Draw on full image — offset y by crop_offset_px
        oy1, oy2 = y1 + crop_offset_px, y2 + crop_offset_px
        oy_c = int(cy * h) + crop_offset_px
        cv2.rectangle(out, (x1, oy1), (x2, oy2), colour, 2)
        cv2.circle(out, (int(cx * w), oy_c), 6, colour, -1)
        cls_name = class_names.get(int(box.cls), str(int(box.cls)))
        label = f"{cls_name} {conf:.2f} cy={cy:.3f}"
        if reason:
            label += f" [{reason}]"
        else:
            label += " [KEPT]"
        cv2.putText(out, label, (x1, max(oy1 - 8, 16)),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.55, colour, 2)
        status = "REJECT" if reason else "KEEP  "
        print(f"  {status}  {cls_name:<12} conf={conf:.3f}  cx={cx:.3f}  cy={cy:.3f}  "
              f"box=({x1},{y1})-({x2},{y2})  reason={reason or '-'}")

    cv2.imwrite(str(output_path), out)
    print(f"\nAnnotated image saved to {output_path}")
    print("(green=kept, red=excluded by zone, orange=rejected by background, dot=centre)")


# ── Background model ───────────────────────────────────────────────────────────

def build_background(paths, n_samples=300, max_long_edge=960):
    """
    Compute a median background image from a random sample of frames.

    Frames are resized to at most max_long_edge pixels on the long side before
    stacking, keeping memory use manageable even for 4K sources (~800 MB for
    300 frames at 960×540).  The background subtraction logic resizes the saved
    background back up to match each source image at detection time.

    Because people appear in only a tiny fraction of frames, the per-pixel
    median of a large sample is an excellent approximation of the empty scene
    under varying lighting conditions.
    """
    sample = random.sample(list(paths), min(n_samples, len(paths)))
    print(f"Building background from {len(sample)} frames...", flush=True)

    target_size = None  # set from first successful frame
    frames = []
    for i, path in enumerate(sample):
        try:
            raw = Path(path).read_bytes()
            img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
            if img is None:
                continue
            h, w = img.shape[:2]
            if target_size is None:
                scale = min(1.0, max_long_edge / max(h, w))
                target_size = (int(w * scale), int(h * scale))
            img = cv2.resize(img, target_size)
            frames.append(img.astype(np.float32))
        except Exception:
            pass
        if (i + 1) % 50 == 0:
            print(f"\r  {i + 1}/{len(sample)} sampled", end="", flush=True)

    if not frames:
        print("No usable frames found.")
        return None

    bg = np.median(np.stack(frames, axis=0), axis=0).astype(np.uint8)
    print(f"\nBackground built from {len(frames)} frames at {target_size[0]}×{target_size[1]}.")
    return bg


# ── Main scan ──────────────────────────────────────────────────────────────────

def scan_folder(folder, threshold=0.0, limit=50, day_only=False, civil_day=False,
                exclude_zones=None, background_path=None,
                fg_overlap=0.15, bg_diff_threshold=25, workers=None,
                date_before=None, date_after=None, crop_top=0.0):
    if not Path(folder).is_dir():
        print(f"Error: folder not found: {folder}")
        return [], set()

    print("Collecting file list...", end="", flush=True)
    paths = []
    all_months = set()  # every month present in the folder, regardless of time filter
    skipped_time = 0
    skipped_date = 0
    for path in Path(folder).rglob("*.jpg"):
        if "mini" in str(path):
            continue
        stem = path.stem
        dt = parse_dt_from_stem(stem)
        date_str = stem[:8]
        if date_before and date_str >= date_before:
            skipped_date += 1
            continue
        if date_after and date_str < date_after:
            skipped_date += 1
            continue
        if dt:
            all_months.add(dt.strftime("%Y%m"))
        if (day_only or civil_day) and dt:
            depression = 6 if civil_day else 12
            if not is_daytime(dt, depression=depression):
                skipped_time += 1
                continue
        paths.append(path)
        if len(paths) % 500 == 0:
            print(f"\rCollecting file list... {len(paths)} found", end="", flush=True)

    total = len(paths)
    date_note = f", {skipped_date} by date filter" if skipped_date else ""
    print(f"\rFound {total} images to scan ({skipped_time} skipped by time filter{date_note})    ")

    if exclude_zones:
        print(f"Exclusion zones ({len(exclude_zones)}): {exclude_zones}")
    if background_path:
        print(f"Background model: {background_path}")

    results = []
    scanned = 0
    tick = 0
    spinner = ["-", "\\", "|", "/"]
    interrupted = False

    num_workers = workers if workers is not None else multiprocessing.cpu_count()
    try:
        with multiprocessing.Pool(
            processes=num_workers,
            initializer=_worker_init,
            initargs=(background_path, exclude_zones or [], fg_overlap, bg_diff_threshold, crop_top),
        ) as pool:
            for score, path in pool.imap_unordered(_score_worker, paths, chunksize=1):
                scanned += 1
                tick += 1
                if score >= threshold:
                    results.append((score, path))
                print(
                    f"\r  {spinner[tick % 4]} {scanned}/{total} scanned, "
                    f"{len(results)} above threshold",
                    end="", flush=True,
                )
    except KeyboardInterrupt:
        interrupted = True
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
    return results, all_months, interrupted


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Scan webcam images for people using YOLOv8.")
    parser.add_argument("folder", help="Folder to scan (or sample from, with --build-background)")
    parser.add_argument("--threshold", type=float, default=0.0,
                        help="Minimum detection confidence to include (0–1, default 0)")
    parser.add_argument("--limit", type=int, default=50,
                        help="Cap the stdout report at N results (does not affect JSON output)")
    parser.add_argument("--day", action="store_true",
                        help="Only scan images taken during daylight (nautical twilight, 12° depression)")
    parser.add_argument("--civil-day", action="store_true",
                        help="Like --day but uses civil twilight (6° depression) — fewer low-light false positives")
    parser.add_argument("--workers", type=int, default=None,
                        help="Number of parallel workers (default: all CPU cores; try 1–2 for network drives)")

    # False-positive suppression
    parser.add_argument("--exclude-zone", metavar="x1,y1,x2,y2", action="append",
                        help="Ignore detections whose centre falls in this zone (fractions 0–1). "
                             "Repeatable. Recommended: "
                             "new cam: '0.0,0.0,1.0,0.60' + '0.0,0.60,0.45,0.68' (sky/water), "
                             "old cam: '0.0,0.0,1.0,0.68' (sky/water), "
                             "'0.52,0.70,0.61,0.81' (boathouse), "
                             "'0.40,0.88,0.46,0.99' (poles)")
    parser.add_argument("--background", metavar="FILE",
                        help="Background model PNG. If FILE does not exist it is built automatically "
                             "from --bg-samples frames and saved.")
    parser.add_argument("--build-background", metavar="FILE",
                        help="Build background model from a sample of images, save to FILE, then exit.")
    parser.add_argument("--bg-samples", type=int, default=300,
                        help="Frames to sample when building the background (default 300)")
    parser.add_argument("--bg-diff", type=int, default=25,
                        help="Pixel intensity diff to consider a region changed from background (default 25)")
    parser.add_argument("--fg-overlap", type=float, default=0.15,
                        help="Min fraction of a detection box that must be in foreground (default 0.15)")

    parser.add_argument("--crop-top", type=float, default=0.0, metavar="FRAC",
                        help="Crop this fraction from the top of each image before inference "
                             "(e.g. 0.67 removes sky/sea/mountains). Exclusion zones are "
                             "remapped automatically to cropped coordinates.")
    parser.add_argument("--before", metavar="YYYYMMDD",
                        help="Only scan images before this date (exclusive upper bound)")
    parser.add_argument("--after", metavar="YYYYMMDD",
                        help="Only scan images from this date onward (inclusive lower bound)")

    parser.add_argument("--annotate", nargs=2, metavar=("IMAGE", "OUTPUT"),
                        help="Diagnostic: annotate a single image with YOLO boxes and zone overlays, "
                             "save to OUTPUT, then exit. Respects --exclude-zone and --background.")
    parser.add_argument("--json-output", metavar="FILE",
                        help="Write results as JSON to FILE (sorted by timestamp, all results above threshold)")
    parser.add_argument("--append", action="store_true",
                        help="Upsert entries by timestamp instead of replacing the whole scanned month")

    args = parser.parse_args()

    # Parse exclusion zones
    exclude_zones = []
    for zone_str in (args.exclude_zone or []):
        try:
            parts = [float(v) for v in zone_str.split(",")]
            if len(parts) != 4:
                raise ValueError
            exclude_zones.append(tuple(parts))
        except ValueError:
            print(f"Invalid --exclude-zone '{zone_str}': expected x1,y1,x2,y2 as fractions 0–1")
            raise SystemExit(1)

    # ── Annotate mode (diagnostic) ────────────────────────────────────────────
    if args.annotate:
        image_in, image_out = args.annotate
        bg = args.background if args.background and Path(args.background).exists() else None
        annotate_image(image_in, image_out,
                       exclude_zones=exclude_zones, background_path=bg,
                       fg_overlap=args.fg_overlap, bg_diff_threshold=args.bg_diff,
                       crop_top=args.crop_top)
        raise SystemExit(0)

    # ── Build-background mode ──────────────────────────────────────────────────
    if args.build_background:
        all_paths = [p for p in Path(args.folder).rglob("*.jpg") if "mini" not in str(p)]
        print(f"Found {len(all_paths)} images in {args.folder}")
        bg = build_background(all_paths, n_samples=args.bg_samples)
        if bg is not None:
            cv2.imwrite(args.build_background, bg)
            print(f"Background saved to {args.build_background}")
        raise SystemExit(0)

    # ── Auto-build background if --background file is missing ─────────────────
    background_path = None
    if args.background:
        bg_file = Path(args.background)
        if not bg_file.exists():
            print(f"Background file not found — building from {args.folder} ...")
            all_paths = [p for p in Path(args.folder).rglob("*.jpg") if "mini" not in str(p)]
            bg = build_background(all_paths, n_samples=args.bg_samples)
            if bg is not None:
                cv2.imwrite(args.background, bg)
                print(f"Background saved to {args.background}")
        if bg_file.exists():
            background_path = args.background

    # ── Scan ──────────────────────────────────────────────────────────────────
    results, scanned_months, interrupted = scan_folder(
        args.folder,
        threshold=args.threshold,
        limit=args.limit,
        day_only=args.day,
        civil_day=args.civil_day,
        exclude_zones=exclude_zones,
        background_path=background_path,
        fg_overlap=args.fg_overlap,
        bg_diff_threshold=args.bg_diff,
        workers=args.workers,
        date_before=args.before,
        date_after=args.after,
        crop_top=args.crop_top,
    )

    if interrupted:
        print("\nJSON not written — scan was interrupted.")
        raise SystemExit(1)

    if args.json_output:
        new_data = sorted(
            [{"timestamp": path.stem, "score": round(score, 4)} for score, path in results],
            key=lambda x: x["timestamp"]
        )
        output_path = Path(args.json_output)
        output_path.parent.mkdir(parents=True, exist_ok=True)
        if output_path.exists():
            existing = json.loads(output_path.read_text())
            if args.append:
                by_ts = {x["timestamp"]: x for x in existing}
                for item in new_data:
                    by_ts[item["timestamp"]] = item
                merged = sorted(by_ts.values(), key=lambda x: x["timestamp"])
                output_path.write_text(json.dumps(merged, indent=2))
                print(f"\nJSON updated in {args.json_output} ({len(merged)} total entries, {len(new_data)} new/updated)")
            else:
                kept = [x for x in existing if x["timestamp"][:6] not in scanned_months]
                merged = sorted(kept + new_data, key=lambda x: x["timestamp"])
                output_path.write_text(json.dumps(merged, indent=2))
                print(f"\nJSON merged into {args.json_output} ({len(merged)} total entries, {len(new_data)} from this scan)")
        else:
            output_path.write_text(json.dumps(new_data, indent=2))
            print(f"\nJSON written to {args.json_output} ({len(new_data)} entries)")
