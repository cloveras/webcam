#!/usr/bin/env python3
"""
Script to delete old webcam images that fall outside the display interval.

For each day, this script calculates the dawn and dusk times (the display interval
shown on the website) and identifies images that fall outside this interval.
These images are not displayed on the website and can be safely deleted to save disk space.

Usage:
    python3 delete_old_images.py [--delete] [--year-filter YYYY/MM] [--min-age-years N]

Options:
    --delete              Actually delete files (default is dry-run mode)
    --year-filter YYYY/MM Only process images in this year/month (e.g., "2018/03")
    --min-age-years N     Only process images older than N years (default: 5)
"""

import os
import sys
import glob
import argparse
from datetime import datetime, timedelta
import math


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
    
    def __init__(self, base_dir=".", dry_run=True, min_age_years=5):
        self.base_dir = base_dir
        self.dry_run = dry_run
        self.min_age_years = min_age_years
        self.sun_calculator = SunCalculator(
            WebcamConfig.LATITUDE,
            WebcamConfig.LONGITUDE
        )
        self.stats = {
            'total_files': 0,
            'files_to_delete': 0,
            'total_size': 0,
            'size_to_delete': 0
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
        
        Args:
            year_month_filter: Optional "YYYY/MM" string to filter by
        
        Returns:
            list of file paths to delete
        """
        images_to_delete = []
        
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
            
            for image_path in images:
                # Skip mini directory images (we'll handle them separately)
                if '/mini/' in image_path:
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
                    # Construct mini path by inserting 'mini' directory
                    dir_name = os.path.dirname(image_path)
                    file_name = os.path.basename(image_path)
                    mini_dir = os.path.join(dir_name, "mini")
                    mini_path = os.path.join(mini_dir, file_name)
                    
                    if os.path.exists(mini_path):
                        images_to_delete.append(mini_path)
                        self.stats['files_to_delete'] += 1
        
        return images_to_delete
    
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
                    print(f"Would delete: {image_path} ({self._format_size(file_size)})")
                else:
                    os.remove(image_path)
                    print(f"Deleted: {image_path} ({self._format_size(file_size)})")
            except Exception as e:
                print(f"Error processing {image_path}: {e}", file=sys.stderr)
    
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
    
    args = parser.parse_args()
    
    # Validate year-filter format if provided
    if args.year_filter:
        parts = args.year_filter.split('/')
        if len(parts) != 2 or not parts[0].isdigit() or not parts[1].isdigit():
            print("Error: --year-filter must be in format YYYY/MM (e.g., 2018/03)", 
                  file=sys.stderr)
            sys.exit(1)
    
    # Create cleaner instance
    cleaner = ImageCleaner(
        base_dir=args.base_dir,
        dry_run=not args.delete,
        min_age_years=args.min_age_years
    )
    
    print("=" * 70)
    print("WEBCAM IMAGE CLEANUP SCRIPT")
    print("=" * 70)
    print(f"Mode: {'DRY RUN (no files will be deleted)' if cleaner.dry_run else 'DELETE MODE'}")
    print(f"Base directory: {os.path.abspath(cleaner.base_dir)}")
    print(f"Minimum age: {args.min_age_years} years")
    if args.year_filter:
        print(f"Year/Month filter: {args.year_filter}")
    print("=" * 70)
    print()
    
    # Find images to delete
    print("Scanning for images outside display intervals...")
    images_to_delete = cleaner.find_images_to_delete(args.year_filter)
    
    if not images_to_delete:
        print("No images found to delete.")
        return
    
    print(f"\nFound {len(images_to_delete)} images to delete.\n")
    
    # Delete (or list) images
    cleaner.delete_images(images_to_delete)
    
    # Print summary
    cleaner.print_summary()


if __name__ == "__main__":
    main()
