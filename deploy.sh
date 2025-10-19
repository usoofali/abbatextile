#!/bin/bash

# ============================================================================
# Laravel Auto-Deployment Script with Database Migration
# ============================================================================
# Save this as deploy.sh in your repository root
# Make executable: chmod +x deploy.sh
# ============================================================================

echo "========================================="
echo "🚀 Starting Laravel Deployment"
echo "========================================="

# Get the deployment directory
DEPLOY_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DEPLOY_DIR

echo "📁 Deployment directory: $DEPLOY_DIR"
echo "⏰ Deployment started at: $(date)"
echo "🔧 PHP Version: $(php -r "echo PHP_VERSION;")"

# ============================================================================
# PRE-DEPLOYMENT CHECKS
# ============================================================================
echo ""
echo "🔍 Running pre-deployment checks..."

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "❌ ERROR: .env file not found!"
    echo "   Please create .env file before deployment"
    exit 1
fi

# Check if composer.json exists
if [ ! -f "composer.json" ]; then
    echo "❌ ERROR: composer.json not found!"
    exit 1
fi

echo "✅ Pre-deployment checks passed"

# ============================================================================
# MAINTENANCE MODE
# ============================================================================
echo ""
echo "🛑 Enabling maintenance mode..."
php artisan down

# ============================================================================
# GIT UPDATE (if using Git deployment)
# ============================================================================
# Uncomment if you want to pull latest changes via Git
# echo ""
# echo "📥 Pulling latest changes from Git..."
# git pull origin main

# ============================================================================
# COMPOSER DEPENDENCIES
# ============================================================================
echo ""
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

if [ $? -ne 0 ]; then
    echo "❌ Composer installation failed!"
    php artisan up
    exit 1
fi
echo "✅ Composer dependencies installed successfully"

# ============================================================================
# DATABASE MIGRATION
# ============================================================================
echo ""
echo "🗃️ Running database migrations..."

# Check if database connection works
echo "   Testing database connection..."
php artisan db:show --counts > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "   ✅ Database connection successful"
    
    # Run migrations
    php artisan migrate --force
    
    if [ $? -eq 0 ]; then
        echo "   ✅ Database migrations completed successfully"
        
        # Run seeders if needed (uncomment if you use seeders)
        # echo "   🌱 Running database seeders..."
        # php artisan db:seed --force
        # echo "   ✅ Database seeders completed"
    else
        echo "❌ Database migrations failed!"
        php artisan up
        exit 1
    fi
else
    echo "⚠️  Warning: Database connection failed, skipping migrations"
    echo "   Please check your database configuration in .env file"
fi

# ============================================================================
# STORAGE LINK
# ============================================================================
echo ""
echo "🔗 Setting up storage link..."

# Remove existing storage link if it's broken or incorrect
if [ -L "public/storage" ] && [ ! -e "public/storage" ]; then
    echo "   Removing broken storage link..."
    rm public/storage
fi

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    php artisan storage:link
    echo "   ✅ Storage link created"
else
    echo "   📝 Storage link already exists"
fi

# ============================================================================
# CACHE OPTIMIZATION
# ============================================================================
echo ""
echo "⚡ Optimizing application cache..."

echo "   Clearing existing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear

echo "   Building optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
# php artisan event:cache  # Uncomment if you use event caching

echo "✅ Application cache optimized"

# ============================================================================
# FILE PERMISSIONS
# ============================================================================
echo ""
echo "🔒 Setting file permissions..."

# Make storage and cache directories writable
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Set appropriate ownership (adjust as needed for your server)
# chown -R www-data:www-data storage/
# chown -R www-data:www-data bootstrap/cache/

echo "✅ File permissions set"

# ============================================================================
# CLEANUP AND OPTIMIZATION
# ============================================================================
echo ""
echo "🧹 Performing cleanup..."

# Clear any temporary files
php artisan optimize:clear

# Cache the configuration again to ensure it's fresh
php artisan config:cache

echo "✅ Cleanup completed"

# ============================================================================
# DISABLE MAINTENANCE MODE
# ============================================================================
echo ""
echo "✅ Disabling maintenance mode..."
php artisan up

# ============================================================================
# POST-DEPLOYMENT CHECKS
# ============================================================================
echo ""
echo "🔍 Running post-deployment checks..."

# Check if application key exists
if [ -z "$(grep APP_KEY=.base64 .env)" ]; then
    echo "⚠️  Warning: Application key may not be set"
    echo "   Run: php artisan key:generate"
fi

# Check storage link
if [ -L "public/storage" ] && [ -e "public/storage" ]; then
    echo "✅ Storage link is working"
else
    echo "❌ Storage link is broken"
fi

# ============================================================================
# DEPLOYMENT COMPLETE
# ============================================================================
echo ""
echo "========================================="
echo "✅ Deployment completed successfully!"
echo "⏰ Finished at: $(date)"
echo "========================================="

# Display application information
echo ""
echo "📊 Application Information:"
php artisan --version
echo "Environment: $(php artisan env)"
echo "Debug mode: $(grep APP_DEBUG .env | cut -d '=' -f2)"

# Display database information
echo ""
echo "🗃️ Database Information:"
php artisan db:show --counts 2>/dev/null || echo "   Database information unavailable"

echo ""
echo "🎉 Deployment finished! Your application is now live."