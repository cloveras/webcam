#!/bin/bash

# Daily people detection scan — runs on Mac where NAS is mounted.
# Scans the current month folder (fast), then rsyncs JSON to the web server.
# At the start of a new month (day 1-2), also scans the previous month folder.
#
# Recommended crontab entry (run at 03:00 every day):
#   0 3 * * * /Users/cl/Dev/webcam/cron/people-scan-daily.sh >> /tmp/people-scan.log 2>&1
#
# Setup: activate the venv once with:
#   cd /Users/cl/Dev/webcam && python3 -m venv venv && source venv/bin/activate
#   pip install ultralytics astral opencv-python numpy

set -euo pipefail

WEBCAM_DIR="/Users/cl/Dev/webcam"
IMAGES_ROOT="/Volumes/homes/cl/Lillevik-webcam"
VENV="$WEBCAM_DIR/venv/bin/python3"
SCRIPT="$WEBCAM_DIR/people_scan.py"

EXCLUDE_ZONES=(
    "--exclude-zone" "0.0,0.0,1.0,0.68"
    "--exclude-zone" "0.52,0.70,0.61,0.81"
    "--exclude-zone" "0.40,0.88,0.46,0.99"
)
THRESHOLD=0.3
WORKERS=2

RSYNC_DEST="lilleviklofoten@login.domeneshop.no:www/webcam/data/"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

scan_month() {
    local year=$1 month=$2
    local folder="$IMAGES_ROOT/$year/$month"
    local json_out="$WEBCAM_DIR/data/people-$year.json"
    local background="$WEBCAM_DIR/data/background-$year.png"

    if [ ! -d "$folder" ]; then
        log "Folder $folder not found, skipping."
        return 0
    fi

    log "Scanning $folder ..."
    nice -n 10 "$VENV" "$SCRIPT" "$folder" \
        --civil-day \
        --threshold "$THRESHOLD" \
        --background "$background" \
        "${EXCLUDE_ZONES[@]}" \
        --workers "$WORKERS" \
        --json-output "$json_out"
    log "Scan done for $year/$month."
}

# Current month
YEAR=$(date '+%Y')
MONTH=$(date '+%m')
DAY=$(date '+%d')

scan_month "$YEAR" "$MONTH"

# At the start of a new month: also re-scan the previous month
if [ "$DAY" -le 2 ]; then
    PREV_MONTH=$(date -v-1m '+%Y %m')
    PREV_YEAR=$(echo "$PREV_MONTH" | awk '{print $1}')
    PREV_MON=$(echo "$PREV_MONTH" | awk '{print $2}')
    scan_month "$PREV_YEAR" "$PREV_MON"
fi

# Deploy JSON files to server
log "Deploying JSON to server..."
rsync -az -e "ssh -p 22" "$WEBCAM_DIR/data/people-"*.json \
    "$RSYNC_DEST"
log "Deploy done."
