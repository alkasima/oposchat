#!/bin/bash
set -e

# Create data directory if it doesn't exist
mkdir -p /data

# Set environment for production database
export DB_CONNECTION=sqlite_production
export DB_DATABASE=/data/database.sqlite

# Check if database exists, if not create it and run migrations
if [ ! -f "/data/database.sqlite" ]; then
    echo "Creating new SQLite database..."
    touch /data/database.sqlite
    chmod 664 /data/database.sqlite
    
    echo "Running migrations..."
    php artisan migrate --force
    
    echo "Database setup complete!"
else
    echo "Database exists, checking for pending migrations..."
    php artisan migrate --force
fi

# Set proper permissions
chmod -R 755 /data

# Execute the main command
exec "$@"