#!/bin/bash

# Configuration
db_user="opentalent_dev"
db_pass="8ther4you"
db_name="opentalent_dev"
backup_dir="/var/www/html/OT2/db_backups"

# Timestamped filename
timestamp=$(date +"%Y-%m-%d")
filename="$backup_dir/${db_name}_backup_$timestamp.sql"

# Create backup
mysqldump -u"$db_user" -p"$db_pass" "$db_name" > "$filename"

# Output result
echo "✅ Database backup complete: $filename"

# Keep only the 7 most recent backups
cd "$backup_dir" || exit
ls -1t ${db_name}_backup_*.sql | tail -n +8 | xargs -r rm -f

