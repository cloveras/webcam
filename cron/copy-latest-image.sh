#!/bin/bash

# Copies the latest image in the "today" directory (YYYY/MM/DD) to "latest.jpg"
# and generates three responsive sizes: latest-resized-650.jpg, -900.jpg, -1800.jpg.
# Picks up both renamed files (2*.jpg) and pre-rename files (Lillevik Lofoten_01_*.jpg)
# so the resized images are updated as soon as a new image arrives, not after rename cron.

# Go to the root directory with all files. This is where the "YYYY/MM/DD" folder structure is.
cd /home/1/l/lilleviklofoten/www/webcam

# Find today's year, month and day: "2023/11/17", etc.
today="$(date +'%Y/%m/%d')"

# Does the "today" directory (format YYYY/MM/DD, example: "2023/11/17") exist? If not: Exit.
[ -d "$today" ] || exit

# Find the newest .jpg file: both renamed (2*.jpg) and pre-rename (Lillevik Lofoten_01_*.jpg).
latest=$(ls -t "$today"/2*.jpg "$today"/Lillevik\ Lofoten_01_*.jpg 2>/dev/null | head -n1)

# Exit if no images yet today.
[ -z "$latest" ] && exit

# Copy the latest *.jpg image to latest.jpg in the current directory.
cp "$latest" latest.jpg
touch latest.jpg

# Generate three responsive sizes from latest.jpg.
# Dimensions: 16:9 aspect ratio (matches 2026+ 4K Lillevik camera).
convert latest.jpg -quality 85 -resize 650x366   latest-resized-650.jpg
convert latest.jpg -quality 85 -resize 900x506   latest-resized-900.jpg
convert latest.jpg -quality 85 -resize 1800x1013 latest-resized-1800.jpg
