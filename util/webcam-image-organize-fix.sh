#!/bin/bash

# Source directory
src_dir="/Volumes/Klumpen/webcam-reolink"

# Destination directory
dest_dir="/Volumes/Klumpen/webcam-reolink-fixed"

# Iterate through files in the source directory
find "$src_dir" \( -type f -name "image-*.jpg" -or -type f -path "*/small/image-*.jpg" \) |  while read -r file; do
    # Extract date and time from the file name
    base_name=$(basename "$file")
    date_time_part=$(echo "$base_name" | cut -d'-' -f2 | cut -d'.' -f1)

    # Truncate the filename to match "YYYYMMDDHHMMSS" format
    truncated_date_time="${date_time_part:0:14}"

    # Extract components from the truncated date and time
    year="${truncated_date_time:0:4}"
    month="${truncated_date_time:4:2}"
    day="${truncated_date_time:6:2}"
    hour="${truncated_date_time:8:2}"
    minute="${truncated_date_time:10:2}"
    second="${truncated_date_time:12:2}"

    # Determine the subdirectory based on the file path
    if [[ $file == *"/small/"* ]]; then
        subdirectory="mini"
    else
        subdirectory=""
    fi

    # Create destination directory if it doesn't exist
    mkdir -p "$dest_dir/$year/$month/$day/$subdirectory"

    # Copy the file to the destination directory
    cp -f -p "$file" "$dest_dir/$year/$month/$day/$subdirectory/$truncated_date_time.jpg"
    echo cp -f -p "$file" "$dest_dir/$year/$month/$day/$subdirectory/$truncated_date_time.jpg"

done

# Add comment to the renamed files
exiftool -r -Description='Lillevik Lofoten webcam: https://lilleviklofoten.no/webcam/' -overwrite_original $dest_dir

# ncfpput all files to new server..
# ncftpput -R -A -v -z -u lilleviklofoten -p PASSWORD ftp.domeneshop.no /www/webcam/ $dest_dir 
