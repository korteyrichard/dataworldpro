#!/bin/bash

# Agent System Rollback Script
# This script safely rolls back the agent system migrations

echo "Starting Agent System Rollback..."

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    echo "Error: Please run this script from the Laravel project root directory"
    exit 1
fi

# Rollback migrations in reverse order
echo "Rolling back agent system migrations..."

php artisan migrate:rollback --path=database/migrations/2025_01_28_100006_create_referral_commissions_table.php
php artisan migrate:rollback --path=database/migrations/2025_01_28_100005_create_referrals_table.php
php artisan migrate:rollback --path=database/migrations/2025_01_28_100004_create_withdrawals_table.php
php artisan migrate:rollback --path=database/migrations/2025_01_28_100003_create_commissions_table.php
php artisan migrate:rollback --path=database/migrations/2025_01_28_100002_add_agent_id_to_orders_table.php
php artisan migrate:rollback --path=database/migrations/2025_01_28_100001_create_agent_products_table.php
php artisan migrate:rollback --path=database/migrations/2025_01_28_100000_create_agent_shops_table.php

echo "Agent system migrations rolled back successfully!"

# Clear cache
echo "Clearing application cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear

echo "Rollback completed successfully!"
echo "Note: You may want to disable agent system features in .env:"
echo "AGENT_SYSTEM_ENABLED=false"