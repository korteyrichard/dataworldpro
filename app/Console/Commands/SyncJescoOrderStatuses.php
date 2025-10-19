<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JescoOrderStatusSyncService;

class SyncJescoOrderStatuses extends Command
{
    protected $signature = 'orders:sync-jesco-status';
    protected $description = 'Sync MTN order statuses with Jesco API';

    public function handle()
    {
        $this->info('Starting Jesco order status sync...');
        
        $syncService = new JescoOrderStatusSyncService();
        $syncService->syncOrderStatuses();
        
        $this->info('Jesco order status sync completed.');
    }
}