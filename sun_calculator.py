"""
sun_calculator.py

Python equivalent of SunCalculator.php — calculates dawn, dusk, sunrise,
sunset, midnight sun, and polar night for the Lillevik Lofoten webcam location.

Used by aurora_scan.py to determine when it's dark enough for aurora.
Mirrors the logic in SunCalculator.php so both scripts behave consistently.

Requires: astral  (pip install astral)
"""

from datetime import date, datetime, time
from zoneinfo import ZoneInfo

from astral import LocationInfo
from astral.sun import dawn as astral_dawn, dusk as astral_dusk

# ── Location (mirrors WebcamConfig.php) ───────────────────────────────────────

LATITUDE  = 68.3300814
LONGITUDE = 14.0917529
TIMEZONE  = "Europe/Oslo"

MIDNIGHT_SUN_PERIOD = {"start": (5, 24), "end": (7, 18)}   # May 24 – Jul 18
POLAR_NIGHT_PERIOD  = {"start": (12, 6), "end": (1, 6)}    # Dec 6  – Jan 6

POLAR_NIGHT_FAKE_SUNRISE_HOUR    = 8
POLAR_NIGHT_FAKE_SUNSET_HOUR     = 15
POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS = 2

# ── Internal helpers ───────────────────────────────────────────────────────────

_location = LocationInfo(
    name="Gimsøy", region="Norway",
    timezone=TIMEZONE,
    latitude=LATITUDE, longitude=LONGITUDE,
)
_tz = ZoneInfo(TIMEZONE)


def is_midnight_sun(d: date) -> bool:
    m, day = d.month, d.day
    s, e = MIDNIGHT_SUN_PERIOD["start"], MIDNIGHT_SUN_PERIOD["end"]
    return (m == s[0] and day >= s[1]) or m == 6 or (m == e[0] and day <= e[1])


def is_polar_night(d: date) -> bool:
    m, day = d.month, d.day
    s, e = POLAR_NIGHT_PERIOD["start"], POLAR_NIGHT_PERIOD["end"]
    return (m == s[0] and day >= s[1]) or (m == e[0] and day <= e[1])


def find_sun_times(d: date) -> tuple:
    """
    Return (dawn, dusk, midnight_sun, polar_night) as timezone-aware datetimes.

    Mirrors SunCalculator::findSunTimes() in SunCalculator.php:
    - Midnight sun  → dawn = 00:00:01, dusk = 23:59:59  (always light)
    - Polar night   → dawn/dusk faked around the fake sunrise/sunset hours
    - Normal day    → nautical twilight (12° depression), same as PHP's
                      nautical_twilight_begin / nautical_twilight_end
    """
    ms = is_midnight_sun(d)
    pn = is_polar_night(d)

    def local(h, m=0, s=0):
        return datetime(d.year, d.month, d.day, h, m, s, tzinfo=_tz)

    if ms:
        return local(0, 0, 1), local(23, 59, 59), True, False

    if pn:
        adj = POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS
        dawn = local(POLAR_NIGHT_FAKE_SUNRISE_HOUR - adj)
        dusk = local(POLAR_NIGHT_FAKE_SUNSET_HOUR  + adj)
        return dawn, dusk, False, True

    # Normal day — nautical twilight (12°), matching PHP's nautical_twilight_begin/end
    try:
        dawn = astral_dawn(_location.observer, date=d, tzinfo=_tz, depression=12)
        dusk = astral_dusk(_location.observer, date=d, tzinfo=_tz, depression=12)
    except Exception:
        # Fallback if astral can't compute (shouldn't happen for this location)
        dawn = local(0, 0, 0)
        dusk = local(23, 59, 59)

    return dawn, dusk, False, False


# ── Public helper for aurora_scan.py ──────────────────────────────────────────

def is_aurora_time(dt: datetime) -> bool:
    """
    Return True if the given local datetime is dark enough for aurora to be visible.

    - Midnight sun  → always False  (never dark)
    - Polar night   → always True   (always dark)
    - Normal day    → True when dt is before nautical dawn or after nautical dusk
    """
    d = dt.date()

    if is_midnight_sun(d):
        return False

    dawn, dusk, _ms, polar_night = find_sun_times(d)

    if polar_night:
        return True

    aware = dt.replace(tzinfo=_tz) if dt.tzinfo is None else dt
    return aware < dawn or aware > dusk
