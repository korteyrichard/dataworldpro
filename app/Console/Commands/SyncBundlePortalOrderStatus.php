<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BundlePortalOrderStatusSyncService;

class SyncBundlePortalOrderStatus extends Command
{
    protected $signature = 'orders:sync-bundleportal-status';
    protected $description = 'Sync pending BundlePortal (Telecel/AT/Ishare) order statuses';

    public function handle()
    {
        $this->info('Starting BundlePortal order status sync...');
        (new BundlePortalOrderStatusSyncService())->syncPendingOrders();
        $this->info('BundlePortal order status sync completed.');
    }
}
