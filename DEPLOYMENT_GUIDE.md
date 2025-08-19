# 🚀 SQLite to Fly.io Migration Guide

## Overview
This guide will help you migrate your local SQLite database to Fly.io with persistent storage using mounted volumes.

## Prerequisites
- Fly.io CLI installed and authenticated
- Your app already deployed to Fly.io (oposchat)

## Migration Steps

### 1. Create Fly.io Volume
```bash
# Create a 1GB volume (adjust size as needed)
fly volumes create oposchat_data --region ord --size 1

# Verify volume creation
fly volumes list
```

### 2. Set Environment Variables
```bash
# Run the secrets script (update Stripe keys first!)
./fly-secrets.sh

# Or set manually:
fly secrets set DB_CONNECTION=sqlite_production
fly secrets set DB_DATABASE=/data/database.sqlite
```

### 3. Export Local Database
```bash
# Create SQL dump of local database
sqlite3 database/database.sqlite .dump > local_database_dump.sql
```

### 4. Deploy Updated App
```bash
# Deploy with new configuration
fly deploy
```

### 5. Import Database to Fly.io
```bash
# Copy database dump to Fly.io
cat local_database_dump.sql | fly ssh console -C "cat > /data/import.sql"

# Import database
fly ssh console -C "sqlite3 /data/database.sqlite < /data/import.sql"

# Run migrations to ensure schema is current
fly ssh console -C "cd /var/www && php artisan migrate --force"

# Clean up
fly ssh console -C "rm -f /data/import.sql"
rm local_database_dump.sql
```

### 6. Verify Deployment
```bash
# Check app status
fly status

# View logs
fly logs

# Test database connection
fly ssh console -C "cd /var/www && php artisan tinker --execute='DB::connection()->getPdo()'"
```

## Important Notes

### Database Location
- **Local:** `database/database.sqlite`
- **Fly.io:** `/data/database.sqlite` (persistent volume)

### Environment Differences
- **Local:** Uses `sqlite` connection
- **Fly.io:** Uses `sqlite_production` connection

### Backup Strategy
```bash
# Create backup of Fly.io database
fly ssh console -C "sqlite3 /data/database.sqlite .dump" > backup_$(date +%Y%m%d).sql
```

### Monitoring
```bash
# Check database size
fly ssh console -C "ls -lh /data/database.sqlite"

# Check volume usage
fly volumes list
```

## Troubleshooting

### Database Not Found
If you get database connection errors:
```bash
fly ssh console -C "ls -la /data/"
fly ssh console -C "cd /var/www && php artisan migrate --force"
```

### Permission Issues
```bash
fly ssh console -C "chmod 664 /data/database.sqlite"
fly ssh console -C "chown www-data:www-data /data/database.sqlite"
```

### Volume Issues
```bash
# List volumes
fly volumes list

# Show volume details
fly volumes show oposchat_data

# If needed, destroy and recreate
fly volumes destroy oposchat_data
fly volumes create oposchat_data --region ord --size 1
```

## Production Considerations

1. **Backup Strategy:** Set up regular database backups
2. **Volume Size:** Monitor usage and resize if needed
3. **Stripe Keys:** Use production Stripe keys
4. **SSL:** Ensure HTTPS is properly configured
5. **Monitoring:** Set up application monitoring

## Quick Commands Reference

```bash
# Deploy
fly deploy

# View logs
fly logs

# SSH into app
fly ssh console

# Check status
fly status

# Scale app
fly scale count 1

# Restart app
fly apps restart oposchat
```