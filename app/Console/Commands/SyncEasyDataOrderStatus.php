<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EasyDataOrderStatusSyncService;

class SyncEasyDataOrderStatus extends Command
{
    protected $signature = 'orders:sync-easydata-status';
    protected $description = 'Sync order statuses from EasyData API';

    public function handle()
    {
        $this->info('Starting EasyData order status sync...');
        
        $syncService = new EasyDataOrderStatusSyncService();
        $syncService->syncOrderStatuses();
        
        $this->info('EasyData order status sync completed.');
    }
}