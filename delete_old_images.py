#!/usr/bin/env python3
"""
Script to delete old webcam images that fall outside the display interval.

For each day, this script calculates the dawn and dusk times (the display interval
shown on the website) and identifies images that fall outside this interval.
These images are not displayed on the website and can be safely deleted to save disk space.

Additional space-saving features:
- Keep only one photo per hour (closest to the whole hour)
- Reduce JPG quality to save space

Usage:
    python3 delete_old_images.py [--delete] [--year-filter YYYY/MM] [--min-age-years N]
                                 [--one-per-hour] [--compress-quality QUALITY]

Options:
    --delete              Actually delete files (default is dry-run mode)
    --year-filter YYYY/MM Only process images in this year/month (e.g., "2018/03")
    --min-age-years N     Only process images older than N years (default: 5)
    --one-per-hour        Keep only one photo per hour (closest to whole hour)
    --compress-quality Q  Compress remaining images to quality Q (1-100, e.g., 80)
"""

import os
import sys
import glob
import argparse
from datetime import datetime, timedelta
import math
from collections import defaultdict
try:
    from PIL import Image
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False


class WebcamConfig:
    """Configuration constants matching WebcamConfig.php"""
    
    # Location settings (Årstrandveien 663, 8314 Gimsøysand, Norway)
    LATITUDE = 68.3300814   # North
    LONGITUDE = 14.0917529  # East
    
    # Midnight sun period
    MIDNIGHT_SUN_PERIOD = {
        'start': {'month': 5, 'day': 24},
        'end': {'month': 7, 'day': 18}
    }
    
    # Polar night period
    POLAR_NIGHT_PERIOD = {
        'start': {'month': 12, 'day': 6},
        'end': {'month': 1, 'day': 6}
    }
    
    # Polar night fake times
    POLAR_NIGHT_FAKE_SUNRISE_HOUR = 8
    POLAR_NIGHT_FAKE_SUNSET_HOUR = 15
    POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS = 2


class SunCalculator:
    """
    Python implementation of SunCalculator.php
    Calculates sunrise, sunset, dawn, and dusk times for a given date.
    """
    
    def __init__(self, latitude, longitude):
        self.latitude = latitude
        self.longitude = longitude
    
    def is_midnight_sun(self, date):
        """Check if the given date falls within the midnight sun period"""
        month = date.month
        day = date.day
        
        start = WebcamConfig.MIDNIGHT_SUN_PERIOD['start']
        end = WebcamConfig.MIDNIGHT_SUN_PERIOD['end']
        
        return ((month == start['month'] and day >= start['day']) or
                (month == 6) or
                (month == end['month'] and day <= end['day']))
    
    def is_polar_night(self, date):
        """Check if the given date falls within the polar night period"""
        month = date.month
        day = date.day
        
        start = WebcamConfig.POLAR_NIGHT_PERIOD['start']
        end = WebcamConfig.POLAR_NIGHT_PERIOD['end']
        
        return ((month == start['month'] and day >= start['day']) or
                (month == end['month'] and day <= end['day']))
    
    def find_sun_times(self, date):
        """
        Find sunrise, sunset, dawn, and dusk times for a given date.
        
        Returns:
            tuple: (dawn_datetime, dusk_datetime, midnight_sun, polar_night)
        """
        midnight_sun = self.is_midnight_sun(date)
        polar_night = self.is_polar_night(date)
        
        if midnight_sun:
            return self._get_midnight_sun_times(date, midnight_sun, polar_night)
        elif polar_night:
            return self._get_polar_night_times(date, midnight_sun, polar_night)
        else:
            return self._get_normal_sun_times(date, midnight_sun, polar_night)
    
    def _get_midnight_sun_times(self, date, midnight_sun, polar_night):
        """Get fake sun times for midnight sun period"""
        dawn = datetime.combine(date, datetime.min.time()).replace(second=1)  # 00:00:01
        dusk = datetime.combine(date, datetime.max.time()).replace(microsecond=0)  # 23:59:59
        return (dawn, dusk, midnight_sun, polar_night)
    
    def _get_polar_night_times(self, date, midnight_sun, polar_night):
        """Get fake sun times for polar night period"""
        adjust_hours = WebcamConfig.POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS
        
        sunrise_hour = WebcamConfig.POLAR_NIGHT_FAKE_SUNRISE_HOUR
        sunset_hour = WebcamConfig.POLAR_NIGHT_FAKE_SUNSET_HOUR
        
        # Calculate dawn and dusk hours
        dawn_hour = sunrise_hour - adjust_hours
        dusk_hour = sunset_hour + adjust_hours
        
        # Handle negative dawn hour (would be on previous day)
        if dawn_hour < 0:
            dawn = datetime.combine(date, datetime.min.time())  # 00:00:00
        else:
            dawn = datetime.combine(date, datetime.min.time()).replace(hour=dawn_hour)
        
        # Handle dusk hour overflow (would be on next day)
        if dusk_hour >= 24:
            dusk = datetime.combine(date, datetime.max.time()).replace(microsecond=0)  # 23:59:59
        else:
            dusk = datetime.combine(date, datetime.min.time()).replace(
                hour=dusk_hour, minute=59, second=59)
        
        return (dawn, dusk, midnight_sun, polar_night)
    
    def _get_normal_sun_times(self, date, midnight_sun, polar_night):
        """
        Get actual calculated sun times for normal days.
        Uses NOAA solar calculator algorithm.
        
        Note: Uses a fixed UTC+1 offset for timezone conversion. This doesn't
        account for daylight saving time (Norway uses UTC+2 in summer), but
        provides sufficient accuracy for the purpose of identifying images
        outside the display interval for deletion.
        """
        lat = self.latitude
        lon = self.longitude
        
        # Calculate fractional year in radians
        day_of_year = date.timetuple().tm_yday
        gamma = 2 * math.pi / 365 * (day_of_year - 1)
        
        # Equation of time (in minutes)
        eqtime = 229.18 * (0.000075 + 0.001868 * math.cos(gamma) 
                - 0.032077 * math.sin(gamma) 
                - 0.014615 * math.cos(2 * gamma) 
                - 0.040849 * math.sin(2 * gamma))
        
        # Solar declination angle (in radians)
        decl = 0.006918 - 0.399912 * math.cos(gamma) \
                + 0.070257 * math.sin(gamma) \
                - 0.006758 * math.cos(2 * gamma) \
                + 0.000907 * math.sin(2 * gamma) \
                - 0.002697 * math.cos(3 * gamma) \
                + 0.00148 * math.sin(3 * gamma)
        
        # Hour angle for sunrise/sunset (elevation = -0.833 degrees)
        lat_rad = math.radians(lat)
        cos_hour_angle = (math.cos(math.radians(90.833)) / 
                         (math.cos(lat_rad) * math.cos(decl)) - 
                         math.tan(lat_rad) * math.tan(decl))
        
        # Check for polar night or midnight sun
        if cos_hour_angle > 1:
            # Sun never rises
            return self._get_polar_night_times(date, midnight_sun, polar_night)
        elif cos_hour_angle < -1:
            # Sun never sets
            return self._get_midnight_sun_times(date, midnight_sun, polar_night)
        
        # Hour angle in degrees
        hour_angle = math.degrees(math.acos(cos_hour_angle))
        
        # Sunrise and sunset in minutes from midnight UTC
        sunrise_utc = 720 - 4 * (lon + hour_angle) - eqtime
        sunset_utc = 720 - 4 * (lon - hour_angle) - eqtime
        
        # Convert to local time (Norway is UTC+1/UTC+2, but we'll use UTC+1 for simplicity)
        # For more accuracy, you could use pytz, but this is sufficient
        utc_offset = 60  # UTC+1 in minutes
        sunrise_minutes = sunrise_utc + utc_offset
        sunset_minutes = sunset_utc + utc_offset
        
        # Convert minutes to time
        sunrise_hour = int(sunrise_minutes // 60)
        sunrise_minute = int(sunrise_minutes % 60)
        sunset_hour = int(sunset_minutes // 60)
        sunset_minute = int(sunset_minutes % 60)
        
        # Handle overflow
        if sunrise_hour >= 24:
            sunrise_hour -= 24
        if sunset_hour >= 24:
            sunset_hour -= 24
        if sunrise_hour < 0:
            sunrise_hour += 24
        if sunset_hour < 0:
            sunset_hour += 24
        
        sunrise = datetime.combine(date, datetime.min.time()).replace(
            hour=sunrise_hour, minute=sunrise_minute)
        sunset = datetime.combine(date, datetime.min.time()).replace(
            hour=sunset_hour, minute=sunset_minute)
        
        # Calculate dawn and dusk (nautical twilight approximation)
        # For nautical twilight, use -12 degrees elevation
        cos_hour_angle_twilight = (math.cos(math.radians(102)) / 
                                   (math.cos(lat_rad) * math.cos(decl)) - 
                                   math.tan(lat_rad) * math.tan(decl))
        
        if cos_hour_angle_twilight > 1 or cos_hour_angle_twilight < -1:
            # No nautical twilight, use fixed offset
            adjust_hours = WebcamConfig.POLAR_NIGHT_DAWN_DUSK_ADJUST_HOURS
            dawn = sunrise - timedelta(hours=adjust_hours)
            dusk = sunset + timedelta(hours=adjust_hours)
        else:
            hour_angle_twilight = math.degrees(math.acos(cos_hour_angle_twilight))
            dawn_utc = 720 - 4 * (lon + hour_angle_twilight) - eqtime
            dusk_utc = 720 - 4 * (lon - hour_angle_twilight) - eqtime
            
            dawn_minutes = dawn_utc + utc_offset
            dusk_minutes = dusk_utc + utc_offset
            
            dawn_hour = int(dawn_minutes // 60)
            dawn_minute = int(dawn_minutes % 60)
            dusk_hour = int(dusk_minutes // 60)
            dusk_minute = int(dusk_minutes % 60)
            
            # Handle overflow
            if dawn_hour >= 24:
                dawn_hour -= 24
            if dusk_hour >= 24:
                dusk_hour -= 24
            if dawn_hour < 0:
                dawn_hour = 0
                dawn_minute = 0
            if dusk_hour < 0:
                dusk_hour = 23
                dusk_minute = 59
            
            dawn = datetime.combine(date, datetime.min.time()).replace(
                hour=dawn_hour, minute=dawn_minute)
            dusk = datetime.combine(date, datetime.min.time()).replace(
                hour=dusk_hour, minute=dusk_minute)
        
        # Ensure dawn and dusk are on the same day
        if dawn.date() != date.date():
            dawn = datetime.combine(date, datetime.min.time())
        if dusk.date() != date.date():
            dusk = datetime.combine(date, datetime.max.time()).replace(microsecond=0)
        
        return (dawn, dusk, midnight_sun, polar_night)


class ImageCleaner:
    """Main class for finding and deleting old images outside display intervals"""
    
    def __init__(self, base_dir=".", dry_run=True, min_age_years=5, 
                 one_per_hour=False, compress_quality=None):
        self.base_dir = base_dir
        self.dry_run = dry_run
        self.min_age_years = min_age_years
        self.one_per_hour = one_per_hour
        self.compress_quality = compress_quality
        self.sun_calculator = SunCalculator(
            WebcamConfig.LATITUDE,
            WebcamConfig.LONGITUDE
        )
        self.stats = {
            'total_files': 0,
            'files_to_delete': 0,
            'files_to_compress': 0,
            'total_size': 0,
            'size_to_delete': 0,
            'size_before_compress': 0,
            'size_after_compress': 0
        }
    
    def should_process_year(self, year):
        """Check if year is old enough to process"""
        current_year = datetime.now().year
        return int(year) <= current_year - self.min_age_years
    
    def parse_image_filename(self, filename):
        """
        Extract datetime from image filename.
        
        Args:
            filename: e.g., "20180316000333.jpg"
        
        Returns:
            datetime object or None if parsing fails
        """
        basename = os.path.basename(filename)
        # Extract just the digits from filename
        digits = ''.join(c for c in basename if c.isdigit())
        
        if len(digits) < 14:
            return None
        
        try:
            year = int(digits[0:4])
            month = int(digits[4:6])
            day = int(digits[6:8])
            hour = int(digits[8:10])
            minute = int(digits[10:12])
            second = int(digits[12:14])
            
            return datetime(year, month, day, hour, minute, second)
        except (ValueError, IndexError):
            return None
    
    def find_images_to_delete(self, year_month_filter=None):
        """
        Find all images that fall outside the display interval.
        Optionally keep only one image per hour (closest to whole hour).
        
        Args:
            year_month_filter: Optional "YYYY/MM" string to filter by
        
        Returns:
            tuple: (list of file paths to delete, list of file paths to compress)
        """
        images_to_delete = []
        images_to_compress = []
        
        if year_month_filter:
            # Process only specified year/month
            pattern = os.path.join(self.base_dir, year_month_filter, "*")
            day_dirs = glob.glob(pattern)
        else:
            # Process all years/months that meet age criteria
            year_pattern = os.path.join(self.base_dir, "????")
            year_dirs = glob.glob(year_pattern)
            
            day_dirs = []
            for year_dir in sorted(year_dirs):
                year = os.path.basename(year_dir)
                if not year.isdigit() or not self.should_process_year(year):
                    continue
                
                month_pattern = os.path.join(year_dir, "??")
                month_dirs = glob.glob(month_pattern)
                
                for month_dir in sorted(month_dirs):
                    day_pattern = os.path.join(month_dir, "??")
                    day_dirs.extend(glob.glob(day_pattern))
        
        # Process each day directory
        for day_dir in sorted(day_dirs):
            if not os.path.isdir(day_dir):
                continue
            
            # Parse the directory path to get date
            parts = day_dir.split(os.sep)
            if len(parts) < 3:
                continue
            
            try:
                year = int(parts[-3])
                month = int(parts[-2])
                day = int(parts[-1])
                date = datetime(year, month, day)
            except (ValueError, IndexError):
                continue
            
            # Get sun times for this date
            dawn, dusk, midnight_sun, polar_night = self.sun_calculator.find_sun_times(date)
            
            # Find images in this day directory
            image_pattern = os.path.join(day_dir, "*.jpg")
            images = glob.glob(image_pattern)
            
            # Group images by hour for one-per-hour processing
            if self.one_per_hour:
                images_by_hour = defaultdict(list)
            
            for image_path in images:
                # Skip mini directory images (we'll handle them separately)
                if os.path.sep + 'mini' + os.path.sep in image_path:
                    continue
                
                self.stats['total_files'] += 1
                
                # Parse image timestamp
                image_dt = self.parse_image_filename(image_path)
                if not image_dt:
                    continue
                
                # Check if image is outside display interval
                if image_dt < dawn or image_dt > dusk:
                    images_to_delete.append(image_path)
                    self.stats['files_to_delete'] += 1
                    
                    # Also check for corresponding mini image
                    mini_path = self._get_mini_path(image_path)
                    if os.path.exists(mini_path):
                        images_to_delete.append(mini_path)
                        self.stats['files_to_delete'] += 1
                elif self.one_per_hour:
                    # Group by hour for later processing
                    hour_key = (image_dt.year, image_dt.month, image_dt.day, image_dt.hour)
                    images_by_hour[hour_key].append((image_path, image_dt))
            
            # Process one-per-hour logic
            if self.one_per_hour:
                for hour_key, hour_images in images_by_hour.items():
                    if len(hour_images) <= 1:
                        # Only one image in this hour, keep it for compression
                        if self.compress_quality and hour_images:
                            images_to_compress.append(hour_images[0][0])
                        continue
                    
                    # Find image closest to the whole hour
                    target_time = datetime(hour_key[0], hour_key[1], hour_key[2], hour_key[3], 0, 0)
                    closest_image = min(hour_images, 
                                       key=lambda x: abs((x[1] - target_time).total_seconds()))
                    
                    # Mark closest image for compression (if enabled)
                    if self.compress_quality:
                        images_to_compress.append(closest_image[0])
                    
                    # Delete all others
                    for image_path, image_dt in hour_images:
                        if image_path != closest_image[0]:
                            images_to_delete.append(image_path)
                            self.stats['files_to_delete'] += 1
                            
                            # Also delete corresponding mini image
                            mini_path = self._get_mini_path(image_path)
                            if os.path.exists(mini_path):
                                images_to_delete.append(mini_path)
                                self.stats['files_to_delete'] += 1
            elif self.compress_quality:
                # If not doing one-per-hour but compression is enabled,
                # compress all images within display interval
                for image_path in images:
                    if os.path.sep + 'mini' + os.path.sep in image_path:
                        continue
                    image_dt = self.parse_image_filename(image_path)
                    if image_dt and dawn <= image_dt <= dusk:
                        if image_path not in images_to_delete:
                            images_to_compress.append(image_path)
        
        return images_to_delete, images_to_compress
    
    def _get_mini_path(self, image_path):
        """Get the corresponding mini image path"""
        dir_name = os.path.dirname(image_path)
        file_name = os.path.basename(image_path)
        mini_dir = os.path.join(dir_name, "mini")
        return os.path.join(mini_dir, file_name)
    
    def delete_images(self, images_to_delete):
        """
        Delete the specified images.
        
        Args:
            images_to_delete: list of file paths to delete
        """
        for image_path in images_to_delete:
            try:
                file_size = os.path.getsize(image_path)
                self.stats['size_to_delete'] += file_size
                
                if self.dry_run:
                    pass  # Don't print individual files in dry run
                else:
                    os.remove(image_path)
                    print(f"Deleted: {image_path} ({self._format_size(file_size)})")
            except Exception as e:
                print(f"Error processing {image_path}: {e}", file=sys.stderr)
    
    def compress_images(self, images_to_compress):
        """
        Compress the specified images to the target quality.
        
        Args:
            images_to_compress: list of file paths to compress
        """
        if not PIL_AVAILABLE:
            print("Warning: PIL/Pillow not available, skipping compression", file=sys.stderr)
            return
        
        if not self.compress_quality:
            return
        
        for image_path in images_to_compress:
            try:
                # Get original file size
                original_size = os.path.getsize(image_path)
                self.stats['size_before_compress'] += original_size
                self.stats['files_to_compress'] += 1
                
                if self.dry_run:
                    # In dry run, don't actually compress
                    # Also track mini image size for estimation
                    mini_path = self._get_mini_path(image_path)
                    if os.path.exists(mini_path):
                        mini_size = os.path.getsize(mini_path)
                        self.stats['size_before_compress'] += mini_size
                        self.stats['files_to_compress'] += 1
                else:
                    # Open and re-save with lower quality
                    img = Image.open(image_path)
                    # Save with specified quality, optimize for smaller size
                    img.save(image_path, 'JPEG', quality=self.compress_quality, optimize=True)
                    
                    # Get new file size
                    new_size = os.path.getsize(image_path)
                    self.stats['size_after_compress'] += new_size
                    saved = original_size - new_size
                    print(f"Compressed: {image_path} ({self._format_size(original_size)} → {self._format_size(new_size)}, saved {self._format_size(saved)})")
                    
                    # Also compress corresponding mini image if it exists
                    mini_path = self._get_mini_path(image_path)
                    if os.path.exists(mini_path):
                        mini_original_size = os.path.getsize(mini_path)
                        self.stats['size_before_compress'] += mini_original_size
                        self.stats['files_to_compress'] += 1
                        
                        mini_img = Image.open(mini_path)
                        mini_img.save(mini_path, 'JPEG', quality=self.compress_quality, optimize=True)
                        
                        mini_new_size = os.path.getsize(mini_path)
                        self.stats['size_after_compress'] += mini_new_size
            except Exception as e:
                print(f"Error compressing {image_path}: {e}", file=sys.stderr)
    
    def _format_size(self, size_bytes):
        """Format file size in human-readable format"""
        for unit in ['B', 'KB', 'MB', 'GB']:
            if size_bytes < 1024.0:
                return f"{size_bytes:.1f}{unit}"
            size_bytes /= 1024.0
        return f"{size_bytes:.1f}TB"
    
    def print_summary(self):
        """Print summary statistics"""
        print("\n" + "=" * 70)
        print("SUMMARY")
        print("=" * 70)
        print(f"Mode: {'DRY RUN (no files deleted)' if self.dry_run else 'DELETE MODE'}")
        print(f"Total files examined: {self.stats['total_files']}")
        print(f"Files to delete: {self.stats['files_to_delete']}")
        print(f"Space to free: {self._format_size(self.stats['size_to_delete'])}")
        
        if self.compress_quality:
            print(f"\nFiles to compress: {self.stats['files_to_compress']}")
            if self.dry_run:
                # Estimate compression savings (assume ~50% reduction for quality 80)
                estimated_savings = int(self.stats['size_before_compress'] * 0.5)
                print(f"Estimated space to save via compression: {self._format_size(estimated_savings)}")
                print(f"Total estimated space savings: {self._format_size(self.stats['size_to_delete'] + estimated_savings)}")
            else:
                compress_saved = self.stats['size_before_compress'] - self.stats['size_after_compress']
                print(f"Space saved via compression: {self._format_size(compress_saved)}")
                print(f"Total space saved: {self._format_size(self.stats['size_to_delete'] + compress_saved)}")
        
        if self.dry_run:
            print("\nRun with --delete flag to actually delete these files.")
        print("=" * 70)


def main():
    parser = argparse.ArgumentParser(
        description='Delete old webcam images outside display intervals',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Dry run (list files to delete) for all years older than 5 years
  python3 delete_old_images.py

  # Actually delete files for all years older than 5 years
  python3 delete_old_images.py --delete

  # Process only images from March 2018
  python3 delete_old_images.py --year-filter 2018/03

  # Process images older than 3 years
  python3 delete_old_images.py --min-age-years 3

  # Delete images from January 2018
  python3 delete_old_images.py --delete --year-filter 2018/01
  
  # Keep only one photo per hour (closest to whole hour)
  python3 delete_old_images.py --one-per-hour
  
  # Compress remaining images to quality 80%
  python3 delete_old_images.py --compress-quality 80
  
  # Combine: delete outside interval, keep one per hour, and compress at 80%
  python3 delete_old_images.py --delete --one-per-hour --compress-quality 80
        """
    )
    
    parser.add_argument(
        '--delete',
        action='store_true',
        help='Actually delete files (default is dry-run mode)'
    )
    
    parser.add_argument(
        '--year-filter',
        type=str,
        metavar='YYYY/MM',
        help='Only process images in this year/month (e.g., "2018/03")'
    )
    
    parser.add_argument(
        '--min-age-years',
        type=int,
        default=5,
        metavar='N',
        help='Only process images older than N years (default: 5)'
    )
    
    parser.add_argument(
        '--base-dir',
        type=str,
        default='.',
        help='Base directory containing webcam images (default: current directory)'
    )
    
    parser.add_argument(
        '--one-per-hour',
        action='store_true',
        help='Keep only one photo per hour (closest to whole hour), delete others'
    )
    
    parser.add_argument(
        '--compress-quality',
        type=int,
        metavar='Q',
        help='Compress remaining images to quality Q (1-100, e.g., 80)'
    )
    
    args = parser.parse_args()
    
    # Validate year-filter format if provided
    if args.year_filter:
        parts = args.year_filter.split('/')
        if len(parts) != 2 or not parts[0].isdigit() or not parts[1].isdigit():
            print("Error: --year-filter must be in format YYYY/MM (e.g., 2018/03)", 
                  file=sys.stderr)
            sys.exit(1)
    
    # Validate compress-quality if provided
    if args.compress_quality is not None:
        if args.compress_quality < 1 or args.compress_quality > 100:
            print("Error: --compress-quality must be between 1 and 100", 
                  file=sys.stderr)
            sys.exit(1)
        if not PIL_AVAILABLE:
            print("Error: PIL/Pillow is required for compression. Install with: pip install Pillow", 
                  file=sys.stderr)
            sys.exit(1)
    
    # Create cleaner instance
    cleaner = ImageCleaner(
        base_dir=args.base_dir,
        dry_run=not args.delete,
        min_age_years=args.min_age_years,
        one_per_hour=args.one_per_hour,
        compress_quality=args.compress_quality
    )
    
    print("=" * 70)
    print("WEBCAM IMAGE CLEANUP SCRIPT")
    print("=" * 70)
    print(f"Mode: {'DRY RUN (no files will be deleted)' if cleaner.dry_run else 'DELETE MODE'}")
    print(f"Base directory: {os.path.abspath(cleaner.base_dir)}")
    print(f"Minimum age: {args.min_age_years} years")
    if args.year_filter:
        print(f"Year/Month filter: {args.year_filter}")
    if args.one_per_hour:
        print("One-per-hour mode: ENABLED (keeping only image closest to whole hour)")
    if args.compress_quality:
        print(f"Compression: ENABLED (quality {args.compress_quality})")
    print("=" * 70)
    print()
    
    # Find images to delete
    print("Scanning for images...")
    images_to_delete, images_to_compress = cleaner.find_images_to_delete(args.year_filter)
    
    if not images_to_delete and not images_to_compress:
        print("No images found to process.")
        return
    
    print(f"\nFound {len(images_to_delete)} images to delete.")
    if images_to_compress:
        print(f"Found {len(images_to_compress)} images to compress.")
    print()
    
    # Delete (or list) images
    cleaner.delete_images(images_to_delete)
    
    # Compress images
    if images_to_compress:
        cleaner.compress_images(images_to_compress)
    
    # Print summary
    cleaner.print_summary()


if __name__ == "__main__":
    main()
