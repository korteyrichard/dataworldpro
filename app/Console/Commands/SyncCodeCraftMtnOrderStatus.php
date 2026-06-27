<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CodeCraftMtnOrderStatusSyncService;

class SyncCodeCraftMtnOrderStatus extends Command
{
    protected $signature = 'orders:sync-codecraft-mtn-status';
    protected $description = 'Sync pending CodeCraft MTN order statuses';

    public function handle()
    {
        $this->info('Starting CodeCraft MTN order status sync...');

        $syncService = new CodeCraftMtnOrderStatusSyncService();
        $syncService->syncPendingOrders();

        $this->info('CodeCraft MTN order status sync completed.');
    }
}
