#!/bin/bash

# Production Cache Clear Script
# Run this on your production server to clear all caches

echo "🚀 Starting cache clearing process..."

# Navigate to Laravel directory (adjust path as needed)
cd /path/to/your/app || cd $(dirname "$0")

# Check if we're in a Laravel directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from your Laravel root directory."
    exit 1
fi

echo "📦 Clearing Laravel caches..."

# Clear all Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

echo "✅ Laravel caches cleared"

# Rebuild optimized caches
echo "🔨 Rebuilding optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Optimized caches rebuilt"

# Clear Opcache (if using PHP-FPM)
echo "🔄 Attempting to clear PHP Opcache..."

# Detect PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
echo "   Detected PHP version: $PHP_VERSION"

# Try to restart PHP-FPM (adjust service name as needed)
if systemctl list-units --type=service | grep -q "php.*fpm"; then
    echo "   Restarting PHP-FPM..."
    sudo systemctl restart php${PHP_VERSION}-fpm 2>/dev/null || \
    sudo systemctl restart php-fpm 2>/dev/null || \
    sudo service php${PHP_VERSION}-fpm restart 2>/dev/null || \
    sudo service php-fpm restart 2>/dev/null || \
    echo "   ⚠️  Could not restart PHP-FPM automatically. Please restart manually."
else
    echo "   ⚠️  PHP-FPM not detected. Please restart your PHP service manually."
fi

# If using Apache
if systemctl is-active --quiet apache2; then
    echo "   Restarting Apache..."
    sudo systemctl restart apache2 2>/dev/null || sudo service apache2 restart 2>/dev/null
fi

# If using Nginx (usually doesn't need restart, but reload is safe)
if systemctl is-active --quiet nginx; then
    echo "   Reloading Nginx..."
    sudo systemctl reload nginx 2>/dev/null || sudo service nginx reload 2>/dev/null
fi

echo ""
echo "✅ Cache clearing complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Test the application with a Spanish question outside the syllabus"
echo "   2. Check logs: tail -f storage/logs/laravel.log"
echo "   3. Look for 'EnhancedAIProviderService' and 'not relevant' in logs"
echo ""

