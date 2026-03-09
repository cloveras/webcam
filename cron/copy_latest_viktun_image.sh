#!/bin/bash

# Copies the latest renamed image in today's Viktun directory to latest.jpg.
#
# Add to server crontab (runs every minute):
#   * * * * * /home/1/l/lilleviklofoten/www/webcam/viktun/copy_latest_viktun_image.sh

cd /home/1/l/lilleviklofoten/www/webcam/viktun

today="$(date +'%Y/%m/%d')"
[ -d "$today" ] || exit

latest=$(ls -t "$today"/2*jpg 2>/dev/null | head -n1)
[ -z "$latest" ] && exit

cp "$latest" latest.jpg
touch latest.jpg
