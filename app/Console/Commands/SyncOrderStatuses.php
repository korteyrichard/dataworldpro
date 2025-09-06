<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderStatusSyncService;

class SyncOrderStatuses extends Command
{
    protected $signature = 'orders:sync-status';
    protected $description = 'Sync order statuses with external APIs';

    public function handle()
    {
        $this->info('Starting order status sync...');
        
        $syncService = new OrderStatusSyncService();
        $syncService->syncOrderStatuses();
        
        $this->info('Order status sync completed.');
    }
}