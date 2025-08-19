#!/bin/bash

# Script to migrate local SQLite database to Fly.io

echo "🚀 Migrating SQLite Database to Fly.io"
echo "======================================"

# Step 1: Export local database
echo "1. Exporting local database..."
php artisan db:seed --class=DatabaseSeeder --force 2>/dev/null || echo "No seeders to run"

# Create a SQL dump of your local database
sqlite3 database/database.sqlite .dump > local_database_dump.sql
echo "✅ Local database exported to local_database_dump.sql"

# Step 2: Create volume on Fly.io (if not exists)
echo "2. Creating Fly.io volume..."
fly volumes create oposchat_data --region ord --size 1 2>/dev/null || echo "Volume may already exist"

# Step 3: Deploy the app with new configuration
echo "3. Deploying app to Fly.io..."
fly deploy

# Step 4: Copy database dump to Fly.io
echo "4. Copying database to Fly.io..."
fly ssh console -C "mkdir -p /data"
cat local_database_dump.sql | fly ssh console -C "cat > /data/import.sql"

# Step 5: Import database on Fly.io
echo "5. Importing database on Fly.io..."
fly ssh console -C "sqlite3 /data/database.sqlite < /data/import.sql"

# Step 6: Run migrations to ensure schema is up to date
echo "6. Running migrations on Fly.io..."
fly ssh console -C "cd /var/www && php artisan migrate --force"

# Step 7: Clean up
echo "7. Cleaning up..."
fly ssh console -C "rm -f /data/import.sql"
rm -f local_database_dump.sql

echo "✅ Migration complete!"
echo "Your SQLite database is now running on Fly.io with persistent storage."