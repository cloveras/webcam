#!/bin/bash

# rename_and_make_mini_images.sh
#
# Renames from long to short filenames.
# Generates mini versions in the "mini" subdirectory using convert.

# Go to the directory with all files.
webcam_dir="/home/1/l/lilleviklofoten/www/webcam"

# Find today's year, month and day: "2023/11/14", etc.
today="$(date +'%Y/%m/%d')"

image_dir="$webcam_dir/$today"

# Exit if the "today" directory does not exist.
[ ! -d "$image_dir" ] && exit

cd "$image_dir"

# Rename the long filenames to short and snappy ones
# Long: Lillevik Lofoten_01_20231114134047.jpg
# Short: 20231114134047.jpg
for f in "Lillevik Lofoten_01_"*.jpg; do
    [ -f "$f" ] || continue
    mv "$f" "$(echo "$f" | sed 's/Lillevik Lofoten_01_//')"
done

# Create "mini" directory if it does not exist.
[ ! -d "mini" ] && mkdir "mini"

# For each *.jpg image: Make a mini image in the "mini" subdirectory (unless it already exists).
for f in *.jpg; do
    [ -f "$f" ] || continue
    [ ! -f "mini/$f" ] && convert "$f" -quality 85 -resize 160x120 "mini/$f"
done
