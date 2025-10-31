#!/bin/bash

# Creates/updates a symbolic link "latest.jpg" to point to the latest image file in today's directory (YYYY/MM/DD).
# To eliminate caching: Link to latest.php instead of latest.jpg.

# Go the the root directory with all files. This is where the "YYYY/MM/DD" folder structure is.
cd /home/1/l/lilleviklofoten/www/webcam

# Find today's year, month and day: "2023/11/17", etc.
today="$(date +'%Y/%m/%d')"

# Does the "today" directory (format YYYY/MM/DD, example: "2023/11/17") exist? If not: Exit.
[ -d $today ] || exit

# Find the newest .jpg file: The filenames start with the year, so "2*jpg" is ok for a while.
latest=$(ls -t $today/2*jpg 2>/dev/null | head -n1)

# If no images found, exit
[ -z "$latest" ] && exit

# If latest.jpg exists and is a symbolic link less than 1 minute old, skip the update
if [ -L "latest.jpg" ]; then
    # Check if the symlink is less than 1 minute old
    symlink_age=$(( $(date +%s) - $(stat -c %Y "latest.jpg" 2>/dev/null || echo 0) ))
    if [ "$symlink_age" -lt 60 ]; then
        # Symlink is less than 1 minute old, skip update
        exit 0
    fi
    
    # Check if symlink already points to the latest file
    current_target=$(readlink "latest.jpg")
    if [ "$current_target" = "$latest" ]; then
        # Already pointing to the correct file, no update needed
        exit 0
    fi
fi

# Create or update the symbolic link to point to the latest image
ln -sf "$latest" latest.jpg


