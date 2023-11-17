#!/bin/bash

# rename_and_make_mini_images.sh
#
# Renames from long to short filenames.
# Generates mini versions in the "mini" subdirectory.

# Go the the directory with all files.
webcam_dir="/home/1/l/lilleviklofoten/www/webcam"

# Find today's year, month and day: "2023/11/14", etc.
today="$(date +'%Y/%m/%d')"

# Where are we?
image_dir="$webcam_dir/$today"
# echo $image_dir

# cd to today's directory (Ymd)
cd $image_dir
# echo `pwd`

# Exit of the "today" directory does not exist.
[ ! -d $image_dir ] && exit

# Rename the long filenames to short and snappy ones
# Long: Lillevik Lofoten_01_20231114134047.jpg
# Short: 20231114134047.jpg
FILES=*jpg
for f in $FILES
do
    # echo $f
    if ! [[ $f == Lillevik* ]] ;
    then
	continue
    fi
    mv "$f" "`echo $f | sed "s/Lillevik Lofoten_01_//"`"; 2>&1
done
# All files have now been renamed to short names.

# Create "mini" directory if ot does not exist.
[ ! -d "mini" ] && mkdir "mini"

# For each *.jpg image: Make a mini image in the "mini" subdirectory (unless it exists).
FILES=*jpg
for f in $FILES
do
    # echo "Converting: convert $f -quality 85 -resize 160x120 small/$f 2>&1" 
    [ ! -f "mini/$f" ] && convert "$f" -quality 85 -resize 160x120 "mini/$f" 2>&1
done

# Add comment to the renamed files
exiftool -r -Description='Lillevik Lofoten webcam: https://lilleviklofoten.no/webcam/' -overwrite_original $image_dir > /dev/null