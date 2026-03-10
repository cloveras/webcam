#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import re
import sys
import shlex
import argparse
import subprocess
from datetime import datetime

FNAME_RE = re.compile(r"^\d{14}\.jpg$", re.IGNORECASE)  # YYYYMMDDHHMMSS.jpg

def which(cmd):
    try:
        out = subprocess.run(["which", cmd], stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True)
        path = out.stdout.strip()
        return path if path else None
    except Exception:
        return None

def human(n):
    units = ["B","K","M","G","T"]
    x = float(n)
    for u in units:
        if x < 1024 or u == units[-1]:
            return f"{x:.1f}{u}"
        x /= 1024

def list_day_dirs(root, month_filter):
    if month_filter:
        base = os.path.join(root, month_filter)
        if not os.path.isdir(base):
            return
        for dd in sorted(os.listdir(base)):
            if not re.fullmatch(r"\d{2}", dd):
                continue
            dp = os.path.join(base, dd)
            if os.path.isdir(dp):
                yield dp
        return
    # Full walk: YYYY/MM/DD
    for yy in sorted(os.listdir(root)):
        if not re.fullmatch(r"\d{4}", yy):
            continue
        yp = os.path.join(root, yy)
        if not os.path.isdir(yp):
            continue
        for mm in sorted(os.listdir(yp)):
            if not re.fullmatch(r"\d{2}", mm):
                continue
            mp = os.path.join(yp, mm)
            if not os.path.isdir(mp):
                continue
            for dd in sorted(os.listdir(mp)):
                if not re.fullmatch(r"\d{2}", dd):
                    continue
                dp = os.path.join(mp, dd)
                if os.path.isdir(dp):
                    yield dp

def find_tools():
    # Prefer mogrify (fast, can write to -path), then convert, then Pillow
    mogrify = which("mogrify")
    convert = which("convert")
    return {"mogrify": mogrify, "convert": convert}

def gen_with_mogrify(day_dir, files, width, quality, overwrite):
    """
    Use mogrify with absolute paths and no cwd to avoid path duplication issues.
    Also process in chunks to avoid shell arg length limits.
    """
    mini_dir = os.path.abspath(os.path.join(day_dir, "mini"))
    os.makedirs(mini_dir, exist_ok=True)

    to_process = []
    for fn in files:
        if not FNAME_RE.match(fn):
            continue
        src = os.path.abspath(os.path.join(day_dir, fn))
        dst = os.path.join(mini_dir, fn)
        if (not overwrite) and os.path.exists(dst):
            continue
        # Skip if source disappeared (e.g., concurrent pruning)
        if not os.path.isfile(src):
            continue
        to_process.append(src)

    if not to_process:
        return 0, 0

    written = 0
    bytes_out = 0

    # Chunk to avoid “Argument list too long”
    CHUNK = 200
    for i in range(0, len(to_process), CHUNK):
        chunk = to_process[i:i+CHUNK]
        cmd = [
            "mogrify",
            "-path", mini_dir,
            "-resize", f"{width}x",
            "-quality", str(quality),
        ] + chunk
        try:
            # No cwd; all paths absolute
            subprocess.check_call(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        except subprocess.CalledProcessError as e:
            print(f"  mogrify failed in {day_dir}: {e}", file=sys.stderr)
            # Fall back to per-file convert for this chunk
            w2, b2 = gen_with_convert(day_dir, [os.path.basename(p) for p in chunk], width, quality, overwrite)
            written += w2
            bytes_out += b2
            continue

    # Tally outputs
    for fn in files:
        if not FNAME_RE.match(fn):
            continue
        dst = os.path.join(mini_dir, fn)
        if os.path.isfile(dst):
            written += 1
            try:
                bytes_out += os.path.getsize(dst)
            except Exception:
                pass

    return written, bytes_out


def gen_with_convert(day_dir, files, width, quality, overwrite):
    """
    Convert per-file with absolute paths and no cwd.
    """
    mini_dir = os.path.abspath(os.path.join(day_dir, "mini"))
    os.makedirs(mini_dir, exist_ok=True)

    written = 0
    bytes_out = 0
    for fn in files:
        if not FNAME_RE.match(fn):
            continue
        src = os.path.abspath(os.path.join(day_dir, fn))
        dst = os.path.join(mini_dir, fn)
        if (not overwrite) and os.path.exists(dst):
            continue
        if not os.path.isfile(src):
            continue
        cmd = [
            "convert", src,
            "-resize", f"{width}x",
            "-quality", str(quality),
            dst
        ]
        try:
            subprocess.check_call(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            if os.path.isfile(dst):
                written += 1
                try:
                    bytes_out += os.path.getsize(dst)
                except Exception:
                    pass
        except subprocess.CalledProcessError as e:
            print(f"  convert failed for {src}: {e}", file=sys.stderr)
    return written, bytes_out

def gen_with_convert(day_dir, files, width, quality, overwrite):
    mini_dir = os.path.join(day_dir, "mini")
    os.makedirs(mini_dir, exist_ok=True)

    written = 0
    bytes_out = 0
    for fn in files:
        if not FNAME_RE.match(fn):
            continue
        src = os.path.join(day_dir, fn)
        dst = os.path.join(mini_dir, fn)
        if (not overwrite) and os.path.exists(dst):
            continue
        cmd = [
            "convert", src,
            "-resize", f"{width}x",
            "-quality", str(quality),
            dst
        ]
        try:
            subprocess.check_call(cmd, cwd=day_dir, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            if os.path.isfile(dst):
                written += 1
                try:
                    bytes_out += os.path.getsize(dst)
                except Exception:
                    pass
        except subprocess.CalledProcessError as e:
            print(f"  convert failed for {src}: {e}", file=sys.stderr)
    return written, bytes_out

# Optional Pillow fallback (only if installed)
try:
    from PIL import Image
    PIL_OK = True
except Exception:
    PIL_OK = False

def gen_with_pillow(day_dir, files, width, quality, overwrite):
    if not PIL_OK:
        print("ERROR: Neither ImageMagick nor Pillow available.", file=sys.stderr)
        return 0, 0
    mini_dir = os.path.join(day_dir, "mini")
    os.makedirs(mini_dir, exist_ok=True)

    written = 0
    bytes_out = 0
    for fn in files:
        if not FNAME_RE.match(fn):
            continue
        src = os.path.join(day_dir, fn)
        dst = os.path.join(mini_dir, fn)
        if (not overwrite) and os.path.exists(dst):
            continue
        try:
            with Image.open(src) as im:
                w, h = im.size
                if w <= width:
                    # still re-encode at quality for consistency
                    im.save(dst, "JPEG", quality=quality, optimize=True)
                else:
                    new_h = int(h * (width / float(w)))
                    im = im.resize((width, new_h), Image.LANCZOS)
                    im.save(dst, "JPEG", quality=quality, optimize=True)
            if os.path.isfile(dst):
                written += 1
                try:
                    bytes_out += os.path.getsize(dst)
                except Exception:
                    pass
        except Exception as e:
            print(f"  PIL failed for {src}: {e}", file=sys.stderr)
    return written, bytes_out

def main():
    ap = argparse.ArgumentParser(
        description="Regenerate 'mini' folders under webcam YYYY/MM/DD. Writes mini/<same filename>.jpg"
    )
    ap.add_argument("--root", default=".", help="Root webcam dir (default: .)")
    ap.add_argument("--month", dest="month", help='Only process subtree YYYY/MM (e.g. 2018/03)')
    ap.add_argument("--width", type=int, default=1024, help="Target width in pixels (default: 1024)")
    ap.add_argument("--quality", type=int, default=80, help="JPEG quality (default: 80)")
    ap.add_argument("--overwrite", action="store_true", help="Overwrite existing files in mini/")
    ap.add_argument("--dry-run", action="store_true", help="List what would be done, don’t write files")
    args = ap.parse_args()

    tools = find_tools()
    use = None
    if tools["mogrify"]:
        use = "mogrify"
    elif tools["convert"]:
        use = "convert"
    elif PIL_OK:
        use = "pillow"

    if use is None:
        print("ERROR: Need one of ImageMagick (mogrify/convert) or Pillow installed.", file=sys.stderr)
        sys.exit(2)

    print(f"Using: {use}  (mogrify={tools['mogrify']}, convert={tools['convert']})")
    print(f"Root: {os.path.abspath(args.root)}  | Month filter: {args.month or '(all)'}")
    print(f"Width: {args.width}px  | Quality: {args.quality}  | Overwrite: {args.overwrite}")
    if args.dry_run:
        print("Mode: DRY RUN\n")
    else:
        print()

    total_days = 0
    total_written = 0
    total_bytes = 0

    for day_dir in list_day_dirs(args.root, args.month):
        total_days += 1
        names = sorted(os.listdir(day_dir))
        # source files: jpgs in day_dir (not inside mini)
        srcs = [n for n in names if n.lower().endswith(".jpg") and n != "mini" and os.path.isfile(os.path.join(day_dir, n))]
        if not srcs:
            continue

        if args.dry_run:
            mini_dir = os.path.join(day_dir, "mini")
            need = 0
            for fn in srcs:
                dst = os.path.join(mini_dir, fn)
                if args.overwrite or not os.path.exists(dst):
                    need += 1
            if need:
                rel = os.path.relpath(day_dir, args.root)
                print(f"{rel}: would (re)generate {need} mini file(s)")
            continue

        if use == "mogrify":
            wrote, bytes_out = gen_with_mogrify(day_dir, srcs, args.width, args.quality, args.overwrite)
        elif use == "convert":
            wrote, bytes_out = gen_with_convert(day_dir, srcs, args.width, args.quality, args.overwrite)
        else:
            wrote, bytes_out = gen_with_pillow(day_dir, srcs, args.width, args.quality, args.overwrite)

        if wrote:
            rel = os.path.relpath(day_dir, args.root)
            print(f"{rel}: generated {wrote} mini file(s) — ~{human(bytes_out)}")
            total_written += wrote
            total_bytes += bytes_out

    print("\n==== SUMMARY ====")
    print(f"Days scanned: {total_days}")
    if args.dry_run:
        print("Dry-run only. Re-run without --dry-run to write files.")
    else:
        print(f"Mini files written: {total_written}  | Bytes out: ~{human(total_bytes)}")

if __name__ == "__main__":
    main()
