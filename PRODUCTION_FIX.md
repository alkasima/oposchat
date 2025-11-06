# Production Fix: Syllabus Relevance Check Not Working

## Issue
On production, questions outside the syllabus are being answered, but on local they're correctly rejected.

## Possible Causes

1. **Code not deployed** - The latest code changes might not be on production
2. **Opcache caching old code** - PHP opcache might be serving old cached code
3. **Laravel config cache** - Cached config might have old settings
4. **Route cache** - Cached routes might be pointing to old code

## Solution Steps

### 1. Verify Code is Deployed
Make sure the latest code with the fixes is on production:
```bash
# On production server
cd /path/to/your/app
git pull origin main  # or your branch
```

### 2. Clear All Caches (CRITICAL)
Run these commands on production:

```bash
# Clear application cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear

# Rebuild config cache (if needed)
php artisan config:cache

# Rebuild route cache (if needed)
php artisan route:cache
```

### 3. Clear Opcache (IMPORTANT)
If you're using PHP-FPM, restart it to clear opcache:
```bash
# Ubuntu/Debian
sudo systemctl restart php8.1-fpm  # or your PHP version

# Or reload
sudo systemctl reload php8.1-fpm
```

If using Apache:
```bash
sudo systemctl restart apache2
```

If using Nginx with PHP-FPM:
```bash
sudo systemctl restart php-fpm
sudo systemctl reload nginx
```

### 4. Verify EnhancedAIProviderService is Being Used
Check the logs to confirm EnhancedAIProviderService is being used:
```bash
tail -f storage/logs/laravel.log | grep "EnhancedAIProviderService"
```

You should see log entries like:
- "EnhancedAIProviderService: Using enhanced system message"
- "Enhanced context retrieval"
- "Question marked as NOT relevant"

### 5. Check Logs for Relevance Checking
Look for relevance checking logs:
```bash
# Check recent logs
tail -n 100 storage/logs/laravel.log | grep -i "relevance\|not relevant\|spanish"

# Check for errors
tail -n 100 storage/logs/laravel.log | grep -i error
```

### 6. Verify Environment Variables
Make sure production has the correct AI provider settings:
```bash
# Check .env file
cat .env | grep AI_PROVIDER
cat .env | grep OPENAI
```

### 7. Test After Clearing Caches
After clearing caches, test with:
1. Spanish question outside syllabus: "¿Cómo se hace el pan?"
2. Should respond: "La pregunta que estás haciendo no está en el temario..."

## Quick Fix Script
Run this on production:

```bash
#!/bin/bash
cd /path/to/your/app

# Clear all Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache

# Restart PHP-FPM (adjust version as needed)
sudo systemctl restart php8.1-fpm

echo "Caches cleared and PHP-FPM restarted. Please test the application."
```

## If Still Not Working

1. **Check file permissions** - Make sure files are readable
2. **Check if code is actually updated** - Verify the file timestamps
3. **Check for .env differences** - Compare local and production .env
4. **Check database** - Verify namespaces are properly set
5. **Check logs** - Look for any errors preventing the enhanced service from working

