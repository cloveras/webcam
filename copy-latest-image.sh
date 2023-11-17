#!/bin/bash

# Copies the latest *.jpg file in the "today" directory to latest.jpg in the current directory.
# To eliminate caching: Link to latest.php instead of latest.jpg.

# Go the the directory with all files.
cd /home/6/s/superelectric/www/viktun/kamera

# Find today's year, month and day: "20231117", etc.
today="$(date +'%Y%m%d')"

# Does the "today" directory exist? If not: Exit.
[ -d $today ] || exit

# Find the newest .jpg file:
latest=$(ls -t $today/image* | head -n1)

# Copy the latest *.jpg image to latest.jpg in the current directory.
cp "$latest" latest.jpg

# Just to be sure.
touch latest.jpg


