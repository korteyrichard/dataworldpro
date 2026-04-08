<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CodeCraftOrderStatusSyncService;

class SyncCodeCraftOrderStatus extends Command
{
    protected $signature = 'orders:sync-codecraft-status';
    protected $description = 'Sync pending CodeCraft order statuses';

    public function handle()
    {
        $this->info('Starting CodeCraft order status sync...');
        
        $syncService = new CodeCraftOrderStatusSyncService();
        $syncService->syncPendingOrders();
        
        $this->info('CodeCraft order status sync completed.');
    }
}