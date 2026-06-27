<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MasterNetOrderStatusSyncService;

class SyncMasterNetOrderStatus extends Command
{
    protected $signature = 'orders:sync-masternet-status';
    protected $description = 'Sync pending MasterNet DataHub order statuses';

    public function handle()
    {
        $this->info('Starting MasterNet DataHub order status sync...');

        $syncService = new MasterNetOrderStatusSyncService();
        $syncService->syncOrderStatuses();

        $this->info('MasterNet DataHub order status sync completed.');
    }
}
