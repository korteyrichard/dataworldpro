<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MasterNetGhOrderStatusSyncService;

class SyncMasterNetGhOrderStatus extends Command
{
    protected $signature = 'orders:sync-masternetgh-status';
    protected $description = 'Sync pending MasterNetGh order statuses';

    public function handle()
    {
        $this->info('Starting MasterNetGh order status sync...');

        $syncService = new MasterNetGhOrderStatusSyncService();
        $syncService->syncOrderStatuses();

        $this->info('MasterNetGh order status sync completed.');
    }
}
