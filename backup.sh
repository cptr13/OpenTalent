#!/bin/bash

timestamp=$(date +"%Y-%m-%d_%H-%M-%S")
backup_dir="/var/www/html/OT2/revisions/backup_$timestamp"

mkdir -p "$backup_dir"
cp -r /var/www/html/OT2/public "$backup_dir/"
cp -r /var/www/html/OT2/includes "$backup_dir/"
cp -r /var/www/html/OT2/config "$backup_dir/"
cp -r /var/www/html/OT2/ajax "$backup_dir/"
cp -r /var/www/html/OT2/app "$backup_dir/"

echo "✅ Backup complete: $backup_dir"

# Keep only the 10 most recent hourly backups (excluding daily)
cd /var/www/html/OT2/revisions || exit
ls -1dt backup_* | grep -v "daily" | tail -n +11 | xargs -r rm -rf
