#!/bin/bash

# Set environment variables for Fly.io deployment
echo "Setting Fly.io secrets..."

# Database configuration
fly secrets set DB_CONNECTION=sqlite_production
fly secrets set DB_DATABASE=/data/database.sqlite

# App configuration
fly secrets set APP_NAME="OposChat"
fly secrets set APP_ENV=production
fly secrets set APP_DEBUG=false
fly secrets set APP_URL=https://oposchat.fly.dev

# Generate new app key for production
APP_KEY=$(php artisan key:generate --show)
fly secrets set APP_KEY="$APP_KEY"

# Stripe configuration (update with your production keys)
fly secrets set STRIPE_KEY="pk_live_your_live_publishable_key"
fly secrets set STRIPE_SECRET="sk_live_your_live_secret_key"
fly secrets set STRIPE_WEBHOOK_SECRET="whsec_your_live_webhook_secret"

# AI Provider (Gemini)
fly secrets set AI_PROVIDER=gemini
fly secrets set GEMINI_API_KEY="your_gemini_api_key"
fly secrets set GEMINI_MODEL="gemini-1.5-flash"

echo "✅ Secrets configured!"
echo "⚠️  Remember to update Stripe keys with your live/production keys"