import os
import random
import csv
from collections import defaultdict

BASE_DIR = "/home/lilleviklofoten/www/webcam"
SAMPLE_MONTHS = [1, 6, 12]   # Jan, Jun, Dec
SAMPLE_SIZE = 10
OUTPUT_CSV = "image_samples.csv"

def parse_datetime_from_filename(fname):
    # filenames like 20200715000419.jpg → YYYY MM
    try:
        year = int(fname[0:4])
        month = int(fname[4:6])
        return year, month
    except Exception:
        return None, None

samples = defaultdict(list)

for root, _, files in os.walk(BASE_DIR):
    for file in sorted(files):
        if not file.lower().endswith(".jpg"):
            continue
        year, month = parse_datetime_from_filename(file)
        if not year or not month:
            continue
        if month in SAMPLE_MONTHS:
            filepath = os.path.join(root, file)
            size_kb = os.stat(filepath).st_size // 1024
            samples[(year, month)].append((year, month, filepath, size_kb))

# now take a sample from each month
filtered = []
for (year, month), group in samples.items():
    filtered.extend(random.sample(group, min(SAMPLE_SIZE, len(group))))

with open(OUTPUT_CSV, "w", newline="") as f:
    writer = csv.writer(f)
    writer.writerow(["Year", "Month", "Path", "SizeKB"])
    writer.writerows(filtered)

print(f"Saved {len(filtered)} samples to {OUTPUT_CSV}")
