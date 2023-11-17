#!/bin/bash

# Copies the latest *.jpg file in the "today" directory to latest.jpg in the current directory.

# Go the the directory with all files.
cd /home/6/s/superelectric/www/viktun/kamera

# Find today's year, month and day: "20191212", etc.
today="$(date +'%Y%m%d')"

# Does the "today" directory exists?
[ -d $today ] || exit

# Find the newest .jpg file:
latest=$(ls -t $today/image* | head -n1)
# 20191213/image-2019121316523201.jpg
# echo "$latest"

# Copy the latest *.jpg image to latest.jpg in the current directory.
cp "$latest" latest.jpg

# Just to be sure.
touch latest.jpg

