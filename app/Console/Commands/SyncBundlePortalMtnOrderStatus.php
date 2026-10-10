<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BundlePortalMtnOrderStatusSyncService;

class SyncBundlePortalMtnOrderStatus extends Command
{
    protected $signature = 'orders:sync-bundleportal-mtn-status';
    protected $description = 'Sync pending BundlePortal MTN order statuses';

    public function handle()
    {
        $this->info('Starting BundlePortal MTN order status sync...');
        (new BundlePortalMtnOrderStatusSyncService())->syncPendingOrders();
        $this->info('BundlePortal MTN order status sync completed.');
    }
}
