#!/bin/bash

# Agent System Deployment Script
# This script safely deploys the agent system to production

echo "Starting Agent System Deployment..."

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    echo "Error: Please run this script from the Laravel project root directory"
    exit 1
fi

# Backup database before migration
echo "Creating database backup..."
php artisan backup:run --only-db 2>/dev/null || echo "Warning: Database backup failed or backup package not installed"

# Run migrations
echo "Running agent system migrations..."
php artisan migrate --path=database/migrations/2025_01_28_100000_create_agent_shops_table.php
php artisan migrate --path=database/migrations/2025_01_28_100001_create_agent_products_table.php
php artisan migrate --path=database/migrations/2025_01_28_100002_add_agent_id_to_orders_table.php
php artisan migrate --path=database/migrations/2025_01_28_100003_create_commissions_table.php
php artisan migrate --path=database/migrations/2025_01_28_100004_create_withdrawals_table.php
php artisan migrate --path=database/migrations/2025_01_28_100005_create_referrals_table.php
php artisan migrate --path=database/migrations/2025_01_28_100006_create_referral_commissions_table.php

# Clear and cache config
echo "Updating application cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Agent system deployed successfully!"
echo ""
echo "Next steps:"
echo "1. Test the agent registration process"
echo "2. Test mini-shop creation"
echo "3. Test commission calculation"
echo "4. Monitor system performance"
echo ""
echo "To disable features if needed, update .env:"
echo "AGENT_SYSTEM_ENABLED=false"