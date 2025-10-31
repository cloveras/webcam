#!/bin/bash

# Copies the latest 2*.jpg file in the "today" directory (YYYY/MM/DD) to "latest.jpg" in the current directory.
# To eliminate caching: Link to latest.php instead of latest.jpg.

# Go the the root directory with all files. This is where the "YYYY/MM/DD" fglder structure is.
cd /home/1/l/lilleviklofoten/www/webcam

# Find today's year, month and day: "2023/11/17", etc.
today="$(date +'%Y/%m/%d')"

# Does the "today" directory (format YYYY/MM/DD, example: "2023/11/17") exist? If not: Exit.
[ -d $today ] || exit

# Find the newest .jpg file: The filenames start with the year, so "2*jpg" is ok for a while.
latest=$(ls -t $today/2*jpg | head -n1)

# Copy the latest *.jpg image to latest.jpg in the current directory.
cp "$latest" latest.jpg

# Just to be sure.
touch latest.jpg


