#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Prune webcam JPGs using the viewing window shown on your website pages.

Behavior (per day YYYY/MM/DD):
- Fetch https://lilleviklofoten.no/webcam/?type=day&date=YYYYMMDD (configurable)
- Parse: "Displaying photos taken between HH:MM and HH:MM."
- Delete images outside that window
- Inside the window, keep ONE image per hour (first timestamp by default, or closest to HH:00)
- Optionally delete corresponding files in ./mini/
- Optional compression of KEPT images using external tools (jpegoptim/mogrify/cjpeg) or Pillow

Python: 3.8+ (no zoneinfo required)
"""

from __future__ import annotations
import argparse
import os
import re
import sys
import time
import shutil
import subprocess
from datetime import date, datetime, timedelta
from typing import List, Tuple, Optional, Dict
from urllib.request import urlopen, Request
from urllib.error import URLError, HTTPError

# Pillow is OPTIONAL; only used if selected/fallback
try:
    from PIL import Image
    PIL_AVAILABLE = True
except Exception:
    PIL_AVAILABLE = False

# ---------- Config ----------
FNAME_RE = re.compile(r"^(\d{14})\.jpg$", re.IGNORECASE)  # YYYYMMDDHHMMSS.jpg
DISPLAY_RE = re.compile(
    r"Displaying photos taken between\s+(\d{2}:\d{2})\s+and\s+(\d{2}:\d{2})",
    re.IGNORECASE
)

DEFAULT_BASE_URL = "https://lilleviklofoten.no/webcam/?type=day&date={date}"

# ---------- Utils ----------
def human(n: int) -> str:
    units = ["B","K","M","G","T","P"]
    x = float(n)
    for u in units:
        if x < 1024 or u == units[-1]:
            return f"{x:.1f}{u}"
        x /= 1024

def parse_ts_from_name(name: str) -> Optional[datetime]:
    m = FNAME_RE.match(name)
    if not m:
        return None
    # Interpret filename as LOCAL clock time (webcam does this)
    try:
        return datetime.strptime(m.group(1), "%Y%m%d%H%M%S")
    except Exception:
        return None

def size_of(path: str) -> int:
    try:
        return os.path.getsize(path)
    except Exception:
        return 0

def day_dirs(root: str, month_filter: Optional[str]):
    """
    Yield day directories under root: YYYY/MM/DD
    If month_filter is 'YYYY/MM', only iterate that subtree.
    """
    if month_filter:
        base = os.path.join(root, month_filter)
        if not os.path.isdir(base):
            return
        for dd in sorted(os.listdir(base)):
            dp = os.path.join(base, dd)
            if os.path.isdir(dp) and re.fullmatch(r"\d{2}", dd):
                yield dp
        return

    for yy in sorted(os.listdir(root)):
        yp = os.path.join(root, yy)
        if not (os.path.isdir(yp) and re.fullmatch(r"\d{4}", yy)):
            continue
        for mm in sorted(os.listdir(yp)):
            mp = os.path.join(yp, mm)
            if not (os.path.isdir(mp) and re.fullmatch(r"\d{2}", mm)):
                continue
            for dd in sorted(os.listdir(mp)):
                dp = os.path.join(mp, dd)
                if os.path.isdir(dp) and re.fullmatch(r"\d{2}", dd):
                    yield dp

# ---------- Fetch & parse viewing window ----------
def fetch_day_window(base_url: str, y: int, m: int, d: int, timeout: int = 15) -> Optional[Tuple[datetime, datetime]]:
    """
    Fetch the day's page and parse "Displaying photos taken between HH:MM and HH:MM."
    Returns two naive datetime objects (local clock as filenames) on success.
    """
    ymd = f"{y:04d}{m:02d}{d:02d}"
    url = base_url.format(date=ymd)
    try:
        req = Request(url, headers={"User-Agent": "webcam-pruner/1.0"})
        with urlopen(req, timeout=timeout) as resp:
            html = resp.read().decode("utf-8", errors="ignore")
    except (HTTPError, URLError, TimeoutError):
        return None
    except Exception:
        return None

    mobj = DISPLAY_RE.search(html)
    if not mobj:
        return None

    start_s, end_s = mobj.group(1), mobj.group(2)
    try:
        sh, sm = map(int, start_s.split(":"))
        eh, em = map(int, end_s.split(":"))
        start_dt = datetime(y, m, d, sh, sm, 0)
        end_dt   = datetime(y, m, d, eh, em, 59)
        return (start_dt, end_dt)
    except Exception:
        return None

# ---------- Compression backends ----------
def have(prog: str) -> bool:
    return shutil.which(prog) is not None

def compress_with_jpegoptim(path: str, quality: int) -> bool:
    if not have("jpegoptim"):
        return False
    # --max sets upper bound; --strip-all removes metadata; --all-progressive makes progressive JPEGs
    cmd = ["jpegoptim", f"--max={quality}", "--strip-all", "--all-progressive", "--quiet", path]
    try:
        r = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        return r.returncode == 0
    except Exception:
        return False

def compress_with_mogrify(path: str, quality: int) -> bool:
    if not have("mogrify"):
        return False
    # In-place. -strip removes metadata.
    cmd = ["mogrify", "-strip", "-quality", str(quality), path]
    try:
        r = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        return r.returncode == 0
    except Exception:
        return False

def compress_with_cjpeg(path: str, quality: int) -> bool:
    """
    Recompress via djpeg|cjpeg pipeline:
        djpeg input.jpg | cjpeg -quality Q -optimize -progressive > tmp && mv tmp input.jpg
    """
    if not have("cjpeg") or not have("djpeg"):
        return False
    tmp = path + ".tmp_cjpeg"
    try:
        p1 = subprocess.Popen(["djpeg", "-fast", path], stdout=subprocess.PIPE)
        p2 = subprocess.Popen(["cjpeg", "-quality", str(quality), "-optimize", "-progressive"],
                              stdin=p1.stdout, stdout=subprocess.PIPE)
        p1.stdout.close()
        out, _ = p2.communicate()
        if p2.returncode != 0:
            return False
        with open(tmp, "wb") as f:
            f.write(out)
        os.replace(tmp, path)
        return True
    except Exception:
        try:
            if os.path.exists(tmp):
                os.remove(tmp)
        except Exception:
            pass
        return False

def compress_with_pillow(path: str, quality: int) -> bool:
    if not PIL_AVAILABLE:
        return False
    tmp = path + ".tmp_pil"
    try:
        img = Image.open(path)
        img.save(tmp, "JPEG", quality=quality, optimize=True)
        os.replace(tmp, path)
        return True
    except Exception:
        try:
            if os.path.exists(tmp):
                os.remove(tmp)
        except Exception:
            pass
        return False

def compress_jpeg(path: str, quality: int, backend: str) -> bool:
    """
    backend:
      - "external-only": try jpegoptim, mogrify, cjpeg; never Pillow
      - "pillow-only": Pillow only
      - "auto" (default): jpegoptim -> mogrify -> cjpeg -> Pillow
    """
    if backend == "pillow-only":
        return compress_with_pillow(path, quality)

    if backend == "external-only":
        return (compress_with_jpegoptim(path, quality) or
                compress_with_mogrify(path, quality) or
                compress_with_cjpeg(path, quality))

    # auto
    return (compress_with_jpegoptim(path, quality) or
            compress_with_mogrify(path, quality) or
            compress_with_cjpeg(path, quality) or
            compress_with_pillow(path, quality))

# ---------- Purge old mini/ ----------
def purge_old_mini_dirs(root: str, cutoff: date, delete: bool) -> Tuple[int,int]:
    """
    Remove whole ./mini/ subdirs for any day older than cutoff.
    Returns (count_dirs, count_files_estimate_removed_in_dry_run)
    """
    removed_dirs = 0
    removed_files = 0
    for ddir in day_dirs(root, None):
        rel = os.path.relpath(ddir, root)
        parts = rel.split(os.sep)
        try:
            y, m, d = int(parts[0]), int(parts[1]), int(parts[2])
            day_d = date(y, m, d)
        except Exception:
            continue
        if day_d >= cutoff:
            continue
        mini = os.path.join(ddir, "mini")
        if os.path.isdir(mini):
            # Count files for info
            try:
                n_files = sum(1 for _ in os.scandir(mini) if _.is_file())
            except Exception:
                n_files = 0
            if delete:
                try:
                    shutil.rmtree(mini)
                    print(f"PURGED mini/: {rel}/mini  (files: ~{n_files})")
                    removed_dirs += 1
                    removed_files += n_files
                except Exception as e:
                    print(f"  FAILED to remove {rel}/mini: {e}", file=sys.stderr)
            else:
                print(f"Would purge: {rel}/mini  (files: ~{n_files})")
                removed_dirs += 1
                removed_files += n_files
    return removed_dirs, removed_files

# ---------- Main pruning ----------
def main():
    ap = argparse.ArgumentParser(
        description="Prune webcam JPGs based on the website's 'Displaying photos ... between HH:MM and HH:MM' window."
    )
    ap.add_argument("--root", default=".", help="Root webcam dir (default: .)")
    ap.add_argument("--month", help='Only process subtree YYYY/MM (e.g. 2018/03)')
    ap.add_argument("--year-filter", dest="year_filter", help='Alias for --month (YYYY/MM)')
    ap.add_argument("--older-than-years", type=int, default=5,
                    help="Process days strictly older than this many years (default 5)")
    ap.add_argument("--base-url", default=DEFAULT_BASE_URL,
                    help="Day page URL template (default: %(default)s). Must contain {date}.")
    ap.add_argument("--mirror-mini", action="store_true",
                    help="Also delete matching file in ./mini/ when deleting .jpg")
    ap.add_argument("--thin-mode", choices=["first","closest"], default="first",
                    help="Which image to KEEP per hour inside window (default: first)")
    ap.add_argument("--delete", action="store_true", help="Actually delete (default: dry-run)")

    # Compression controls
    ap.add_argument("--compress-quality", type=int,
                    help="Compress KEPT images to this JPEG quality (1-100)")
    ap.add_argument("--apply-compression", action="store_true",
                    help="Actually apply compression (default: estimate only)")
    ap.add_argument("--compress-backend", choices=["auto","external-only","pillow-only"],
                    default="auto",
                    help="Compression backend policy (default: auto)")
    ap.add_argument("--compress-min-bytes", type=int, default=0,
                    help="Skip compression for files smaller than this size (default 0)")
    ap.add_argument("--compress-max-files", type=int, default=0,
                    help="Stop after compressing this many files (0=no limit)")

    # Purge mini/ folders wholesale
    ap.add_argument("--purge-old-mini", action="store_true",
                    help="Purge entire mini/ directories for days older than the cutoff")
    ap.add_argument("--show-detectors", action="store_true",
                    help="Print detected compression tools at startup")

    args = ap.parse_args()
    month_filter = args.month or args.year_filter

    # Safety checks
    if "{date}" not in args.base_url:
        print("ERROR: --base-url must contain '{date}' placeholder (YYYYMMDD).", file=sys.stderr)
        sys.exit(2)

    if args.compress_quality is not None:
        if not (1 <= args.compress_quality <= 100):
            print("ERROR: --compress-quality must be 1..100", file=sys.stderr)
            sys.exit(2)
        if args.compress_backend == "pillow-only" and not PIL_AVAILABLE:
            print("ERROR: Pillow is not available but --compress-backend pillow-only was requested.", file=sys.stderr)
            sys.exit(2)

    # Detect tools
    detectors = {
        "jpegoptim": have("jpegoptim"),
        "mogrify": have("mogrify"),
        "cjpeg": have("cjpeg"),
        "djpeg": have("djpeg"),
        "Pillow": PIL_AVAILABLE,
    }
    if args.show_detectors:
        found = [k for k,v in detectors.items() if v]
        missing = [k for k,v in detectors.items() if not v]
        print(f"Detected: {', '.join(found) if found else '(none)'}")
        if missing:
            print(f"Missing:  {', '.join(missing)}")

    # Compute cutoff date
    today = date.today()
    cutoff = today - timedelta(days=int(365.25 * args.older_than_years))

    # Optional: purge mini/ up-front
    if args.purge_old_mini:
        print(f"== Purging mini/ directories older than {args.older_than_years} years ==")
        dcount, fcount = purge_old_mini_dirs(args.root, cutoff, args.delete)
        if not args.delete:
            print(f"(Dry run) Would purge {dcount} mini/ directories (~{fcount} files)")

    # Main prune
    total_candidates, total_bytes = 0, 0
    total_deleted, total_deleted_bytes = 0, 0

    total_compress, total_before_comp, total_after_comp = 0, 0, 0

    t0 = time.time()

    # tiny cache for fetched windows to avoid re-fetching same day twice
    win_cache: Dict[str, Tuple[datetime, datetime]] = {}

    def maybe_print_progress(prefix: str):
        if time.time() - maybe_print_progress.last > 2.0:
            print(prefix, flush=True)
            maybe_print_progress.last = time.time()
    maybe_print_progress.last = 0.0

    for ddir in day_dirs(args.root, month_filter):
        rel = os.path.relpath(ddir, args.root)
        parts = rel.split(os.sep)
        try:
            y, m, d = int(parts[0]), int(parts[1]), int(parts[2])
            day_d = date(y, m, d)
        except Exception:
            continue

        # Only process days strictly older than cutoff
        if day_d >= cutoff:
            continue

        # Fetch viewing window for this day
        ymd = f"{y:04d}{m:02d}{d:02d}"
        if ymd in win_cache:
            win = win_cache[ymd]
        else:
            win = fetch_day_window(args.base_url, y, m, d)
            if win:
                win_cache[ymd] = win

        if not win:
            # If we cannot get window for this day, skip to be safe
            # (Alternative policy could be "delete-all" — but safer to skip.)
            continue

        win_start, win_end = win

        # Collect files
        try:
            names = sorted(os.listdir(ddir))
        except Exception:
            continue

        inside: List[Tuple[datetime, str, int]] = []
        outside: List[Tuple[str, int]] = []

        for name in names:
            if name == "mini":
                continue
            if not name.lower().endswith(".jpg"):
                continue
            ts = parse_ts_from_name(name)
            if ts is None:
                continue
            p = os.path.join(ddir, name)
            sz = size_of(p)

            if win_start <= ts <= win_end:
                inside.append((ts, p, sz))
            else:
                outside.append((p, sz))
                if args.mirror_mini:
                    mp = os.path.join(ddir, "mini", name)
                    if os.path.isfile(mp):
                        outside.append((mp, size_of(mp)))

        # Thin inside to one per hour
        inside.sort(key=lambda t: t[0])
        keep_by_hour: Dict[Tuple[int,int,int,int], Tuple[datetime,str,int]] = {}

        if args.thin_mode == "first":
            for ts, p, sz in inside:
                key = (ts.year, ts.month, ts.day, ts.hour)
                if key not in keep_by_hour:
                    keep_by_hour[key] = (ts, p, sz)
        else:
            # closest to whole hour
            buckets: Dict[Tuple[int,int,int,int], List[Tuple[datetime,str,int]]] = {}
            for tup in inside:
                ts = tup[0]
                key = (ts.year, ts.month, ts.day, ts.hour)
                buckets.setdefault(key, []).append(tup)
            for key, items in buckets.items():
                target = datetime(key[0], key[1], key[2], key[3], 0, 0)
                chosen = min(items, key=lambda x: abs((x[0]-target).total_seconds()))
                keep_by_hour[key] = chosen

        kept_paths = {v[1] for v in keep_by_hour.values()}
        # Any inside not kept -> delete
        for ts, p, sz in inside:
            if p not in kept_paths:
                outside.append((p, sz))
                if args.mirror_mini:
                    mp = os.path.join(ddir, "mini", os.path.basename(p))
                    if os.path.isfile(mp):
                        outside.append((mp, size_of(mp)))

        if not outside and args.compress_quality is None:
            continue

        # Report this day’s deletion candidates
        cand_bytes = sum(sz for _, sz in outside)
        if outside:
            total_candidates += len(outside)
            total_bytes += cand_bytes
            print(f"\n{rel}: {len(outside)} file(s) " +
                  ("to DELETE" if args.delete else "candidate(s)") +
                  f" — {human(cand_bytes)}")
            for p, sz in outside[:10]:
                print(f"  {p}  ({human(sz)})")
            if len(outside) > 10:
                print(f"  ... and {len(outside)-10} more")

        # Do deletions
        if args.delete:
            for p, sz in outside:
                try:
                    os.remove(p)
                    total_deleted += 1
                    total_deleted_bytes += sz
                except Exception as e:
                    print(f"  FAILED to delete {p}: {e}", file=sys.stderr)
            maybe_print_progress(f"Deleted so far: {total_deleted} files, {human(total_deleted_bytes)}")

        # Compression of KEPT images (original + mini if exists)
        if args.compress_quality is not None:
            to_compress: List[str] = []
            for _, p, _ in keep_by_hour.values():
                if os.path.isfile(p) and os.path.getsize(p) >= (args.compress_min_bytes or 0):
                    to_compress.append(p)
                mp = os.path.join(os.path.dirname(p), "mini", os.path.basename(p))
                if os.path.isfile(mp) and os.path.getsize(mp) >= (args.compress_min_bytes or 0):
                    to_compress.append(mp)

            count_done = 0
            before_sum = 0
            after_sum = 0

            for p in to_compress:
                if args.compress_max_files and count_done >= args.compress_max_files:
                    break
                sz0 = size_of(p)
                before_sum += sz0

                if args.apply_compression:
                    ok = compress_jpeg(p, args.compress_quality, args.compress_backend)
                    sz1 = size_of(p)
                    after_sum += sz1
                    if not ok:
                        print(f"  WARN: compression skipped for {p} (no backend succeeded)", file=sys.stderr)
                else:
                    # Only estimate (assume ~30% reduction; ~20% if quality>85)
                    factor = 0.7 if args.compress_quality <= 85 else 0.8
                    est = int(sz0 * factor)
                    after_sum += est

                count_done += 1

            total_compress += count_done
            total_before_comp += before_sum
            total_after_comp  += after_sum

            maybe_print_progress(f"Compressed so far: {total_compress} files (applied={args.apply_compression})")

    # ---------- Summary ----------
    print("\n==== SUMMARY ====")
    print(f"Candidates: {total_candidates}  | Size: {human(total_bytes)}")
    if args.delete:
        print(f"Deleted:    {total_deleted}  | Freed: {human(total_deleted_bytes)}")
    else:
        print("Dry-run only. Re-run with --delete to actually remove files.")

    if args.compress_quality is not None:
        if args.apply_compression:
            saved = max(0, total_before_comp - total_after_comp)
            print(f"Compressed: {total_compress}  | Saved via compression: {human(saved)}")
        else:
            est_saved = max(0, total_before_comp - total_after_comp)
            print(f"Compression (dry run): would process ~{total_compress} file(s)")
            print(f"Estimated savings: ~{human(est_saved)} at quality {args.compress_quality}")

    print(f"Elapsed: {int(time.time()-t0)}s")


if __name__ == "__main__":
    main()