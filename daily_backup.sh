#!/bin/bash

timestamp=$(date +"%Y-%m-%d")
backup_dir="/var/www/html/OT2/revisions/backup_daily_$timestamp"

mkdir -p "$backup_dir"
cp -r /var/www/html/OT2/public "$backup_dir/"
cp -r /var/www/html/OT2/includes "$backup_dir/"
cp -r /var/www/html/OT2/config "$backup_dir/"
cp -r /var/www/html/OT2/ajax "$backup_dir/"
cp -r /var/www/html/OT2/app "$backup_dir/"

echo "✅ Daily backup complete: $backup_dir"

# Keep only the 7 most recent daily backups
cd /var/www/html/OT2/revisions || exit
ls -1dt backup_daily_* | tail -n +8 | xargs -r rm -rf
