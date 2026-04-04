"""
test_aurora_scores.py

Checks algorithmic behaviour of aurora_scan.py and sun_calculator.py
without depending on specific image files.

Run standalone:  python3 test_aurora_scores.py
Run with pytest: pytest test_aurora_scores.py -v

These are informational checks of the core logic — time filtering, midnight sun,
polar night, and the brightness/scoring envelope. Not a strict CI gate.
"""

import sys
from datetime import date, datetime


# ---------------------------------------------------------------------------
# sun_calculator checks
# ---------------------------------------------------------------------------

def check_sun_calculator():
    from sun_calculator import is_midnight_sun, is_polar_night, is_aurora_time

    checks = [
        # (description, actual, expected)
        # Midnight sun
        ("Jun 15 is midnight sun",        is_midnight_sun(date(2026, 6, 15)), True),
        ("May 23 is NOT midnight sun",    is_midnight_sun(date(2026, 5, 23)), False),
        ("May 24 IS midnight sun",        is_midnight_sun(date(2026, 5, 24)), True),
        ("Jul 18 IS midnight sun",        is_midnight_sun(date(2026, 7, 18)), True),
        ("Jul 19 is NOT midnight sun",    is_midnight_sun(date(2026, 7, 19)), False),
        # Polar night
        ("Dec 20 is polar night",         is_polar_night(date(2025, 12, 20)), True),
        ("Jan 1 is polar night",          is_polar_night(date(2026, 1, 1)),   True),
        ("Jan 6 IS polar night",          is_polar_night(date(2026, 1, 6)),   True),
        ("Jan 7 is NOT polar night",      is_polar_night(date(2026, 1, 7)),   False),
        ("Dec 5 is NOT polar night",      is_polar_night(date(2025, 12, 5)),  False),
        # Midnight sun → aurora impossible
        ("Jun 15 22:00 not aurora time",
            is_aurora_time(datetime(2026, 6, 15, 22, 0)), False),
        # Polar night + fake dawn/dusk: daytime should be excluded
        ("Jan 3 08:30 daytime polar night excluded",
            is_aurora_time(datetime(2026, 1, 3, 8, 30)), False),
        ("Jan 3 12:00 midday polar night excluded",
            is_aurora_time(datetime(2026, 1, 3, 12, 0)), False),
        # Polar night at night should be included
        ("Jan 3 21:00 nighttime polar night included",
            is_aurora_time(datetime(2026, 1, 3, 21, 0)), True),
        ("Jan 3 02:00 nighttime polar night included",
            is_aurora_time(datetime(2026, 1, 3, 2, 0)), True),
        # Normal winter night
        ("Feb 10 22:00 is aurora time",
            is_aurora_time(datetime(2026, 2, 10, 22, 0)), True),
        ("Feb 10 13:00 is NOT aurora time",
            is_aurora_time(datetime(2026, 2, 10, 13, 0)), False),
    ]

    passed = failed = 0
    for desc, actual, expected in checks:
        ok = actual == expected
        marker = "  PASS" if ok else "  FAIL"
        if ok:
            passed += 1
        else:
            failed += 1
        print(f"{marker}  {desc}  (got {actual}, expected {expected})")
    return passed, failed


# ---------------------------------------------------------------------------
# Scoring envelope checks (synthetic images, no files needed)
# ---------------------------------------------------------------------------

def check_scoring_envelope():
    """
    Test the scoring function on synthetic numpy arrays to verify that
    the brightness penalty and hue cap behave as expected.
    """
    import numpy as np
    import cv2
    from aurora_scan import aurora_score
    import tempfile, os

    def make_test_image(sky_v, hue, saturation, patch_fraction, full_sky=False):
        """
        Create a synthetic 640x480 BGR image where the top 65% is sky.
        sky_v: mean V for the sky (0–255)
        hue: hue for the aurora patch (0–180 OpenCV scale)
        saturation: saturation for the patch
        patch_fraction: fraction of sky pixels that are the given hue
        full_sky: if True, fill entire sky with hue (tests global coverage)
        """
        h, w = 480, 640
        img = np.zeros((h, w, 3), dtype=np.uint8)
        sky_h = int(h * 0.65)

        # Dark sky background
        sky_hsv = np.zeros((sky_h, w, 3), dtype=np.uint8)
        sky_hsv[:, :, 2] = sky_v  # V channel
        sky_bgr = cv2.cvtColor(sky_hsv, cv2.COLOR_HSV2BGR)
        img[:sky_h] = sky_bgr

        # Patch of aurora-coloured pixels
        patch_w = int(w * (patch_fraction ** 0.5))
        patch_h = int(sky_h * (patch_fraction ** 0.5))
        patch = np.zeros((patch_h, patch_w, 3), dtype=np.uint8)
        patch[:, :, 0] = hue
        patch[:, :, 1] = saturation
        patch[:, :, 2] = max(sky_v, 40)  # slightly brighter than background
        patch_bgr = cv2.cvtColor(patch, cv2.COLOR_HSV2BGR)
        img[:patch_h, :patch_w] = patch_bgr

        # Write to temp file and score it
        tmp = tempfile.NamedTemporaryFile(suffix='.jpg', delete=False)
        cv2.imwrite(tmp.name, img)
        score = aurora_score(tmp.name)
        os.unlink(tmp.name)
        return score

    THRESHOLD = 0.08
    checks = []

    # Large bright green aurora patch on dark sky → high score
    s = make_test_image(sky_v=15, hue=60, saturation=180, patch_fraction=0.08)
    checks.append(("Large green aurora patch on dark sky scores above threshold",
                   s >= THRESHOLD, True, f"score={s:.3f}"))

    # Same patch on bright sky (twilight) → suppressed by brightness_factor
    s = make_test_image(sky_v=100, hue=60, saturation=180, patch_fraction=0.08)
    checks.append(("Same green patch on bright (twilight) sky is suppressed",
                   s < THRESHOLD, True, f"score={s:.3f}"))

    # High-hue (H 150, blue) patch on dark sky → not detected (above H cap)
    s = make_test_image(sky_v=15, hue=150, saturation=180, patch_fraction=0.08)
    checks.append(("Blue patch (H=150) on dark sky not detected",
                   s < THRESHOLD, True, f"score={s:.3f}"))

    # No aurora pixels at all → near zero (patch_fraction tiny to avoid empty array)
    s = make_test_image(sky_v=10, hue=60, saturation=5, patch_fraction=0.001)
    checks.append(("No aurora pixels → near-zero score",
                   s < 0.05, True, f"score={s:.3f}"))

    passed = failed = 0
    for desc, actual, expected, detail in checks:
        ok = actual == expected
        marker = "  PASS" if ok else "  FAIL"
        if ok:
            passed += 1
        else:
            failed += 1
        print(f"{marker}  {desc}  [{detail}]")
    return passed, failed


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    print("=== sun_calculator logic ===")
    p1, f1 = check_sun_calculator()

    print()
    print("=== scoring envelope (synthetic images) ===")
    p2, f2 = check_scoring_envelope()

    total_p = p1 + p2
    total_f = f1 + f2
    print()
    print(f"Total: {total_p} passed, {total_f} failed")
    return total_f == 0


if __name__ == "__main__":
    ok = main()
    sys.exit(0 if ok else 1)


# ---------------------------------------------------------------------------
# pytest interface (same checks as above)
# ---------------------------------------------------------------------------

try:
    import pytest

    def test_midnight_sun_dates():
        from sun_calculator import is_midnight_sun
        assert is_midnight_sun(date(2026, 6, 15))
        assert is_midnight_sun(date(2026, 5, 24))
        assert is_midnight_sun(date(2026, 7, 18))
        assert not is_midnight_sun(date(2026, 5, 23))
        assert not is_midnight_sun(date(2026, 7, 19))

    def test_polar_night_dates():
        from sun_calculator import is_polar_night
        assert is_polar_night(date(2025, 12, 20))
        assert is_polar_night(date(2026, 1, 1))
        assert is_polar_night(date(2026, 1, 6))
        assert not is_polar_night(date(2026, 1, 7))
        assert not is_polar_night(date(2025, 12, 5))

    def test_aurora_time_midnight_sun():
        from sun_calculator import is_aurora_time
        assert not is_aurora_time(datetime(2026, 6, 15, 22, 0))

    def test_aurora_time_polar_night_daytime_excluded():
        from sun_calculator import is_aurora_time
        assert not is_aurora_time(datetime(2026, 1, 3, 8, 30))
        assert not is_aurora_time(datetime(2026, 1, 3, 12, 0))

    def test_aurora_time_polar_night_nighttime_included():
        from sun_calculator import is_aurora_time
        assert is_aurora_time(datetime(2026, 1, 3, 21, 0))
        assert is_aurora_time(datetime(2026, 1, 3, 2, 0))

    def test_scoring_bright_sky_suppressed():
        import numpy as np, cv2, tempfile, os
        from aurora_scan import aurora_score
        # Green patch on bright sky (high V) → brightness_factor suppresses it
        h, w = 480, 640
        img = np.zeros((h, w, 3), dtype=np.uint8)
        sky_h = int(h * 0.65)
        hsv = np.zeros((sky_h, w, 3), dtype=np.uint8)
        hsv[:, :, 2] = 120   # bright sky
        hsv[:120, :120, 0] = 60   # green hue
        hsv[:120, :120, 1] = 200  # high saturation
        hsv[:120, :120, 2] = 180  # bright patch
        img[:sky_h] = cv2.cvtColor(hsv, cv2.COLOR_HSV2BGR)
        tmp = tempfile.NamedTemporaryFile(suffix='.jpg', delete=False)
        cv2.imwrite(tmp.name, img)
        score = aurora_score(tmp.name)
        os.unlink(tmp.name)
        assert score < 0.08, f"Bright sky score {score:.3f} should be suppressed"

except ImportError:
    pass
