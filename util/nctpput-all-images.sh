#!/bin/bash

# Source directory with all YYYY directories
src_dir="DIRECTORY_WITH_ALL_FILES"

# FTP server details
ftp_server="YOUR_FTP_SERVER"
ftp_user="YOUR_USERNAME"
ftp_password="YOUR_PASSWORD"
ftp_remote_dir="DIRECTORY_TO_UPLOAD_TO"

# Iterate over YYYY directories
for year_dir in "$src_dir"/*/; do
    echo "$year_dir"
    for month_dir in "$year_dir"/*/; do
        remote_dir="$ftp_remote_dir$(basename "$year_dir")"
        ncftpput -R -v -z -u $ftp_user -p $ftp_password $ftp_server $remote_dir $month_dir
    done
done