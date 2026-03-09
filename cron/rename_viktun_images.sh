#!/bin/bash

# rename_viktun_images.sh
#
# Renames Viktun camera files from long to short filenames.
# Generates mini versions in the "mini" subdirectory using convert.
#
# Long:  Viktun_01_20260309125946.jpg
# Short: 20260309125946.jpg
#
# Add to server crontab (runs every minute):
#   * * * * * /home/1/l/lilleviklofoten/www/webcam/viktun/rename_viktun_images.sh

webcam_dir="/home/1/l/lilleviklofoten/www/webcam/viktun"

today="$(date +'%Y/%m/%d')"
image_dir="$webcam_dir/$today"

[ ! -d "$image_dir" ] && exit

cd "$image_dir"

# Rename: strip "Viktun_01_" prefix
for f in Viktun_01_*.jpg; do
    [ -f "$f" ] || continue
    mv "$f" "$(echo "$f" | sed 's/Viktun_01_//')"
done

# Create mini thumbnails
[ ! -d "mini" ] && mkdir "mini"

for f in *.jpg; do
    [ -f "$f" ] || continue
    [ ! -f "mini/$f" ] && convert "$f" -quality 85 -resize 160x120 "mini/$f" 2>&1
done
