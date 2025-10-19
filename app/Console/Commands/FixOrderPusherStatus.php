<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

class FixOrderPusherStatus extends Command
{
    protected $signature = 'orders:fix-pusher-status';
    protected $description = 'Fix orders with null order_pusher_status values';

    public function handle()
    {
        $this->info('Fixing orders with null order_pusher_status...');
        
        $updatedCount = Order::whereNull('order_pusher_status')
            ->update(['order_pusher_status' => 'disabled']);
        
        $this->info("Updated {$updatedCount} orders with null order_pusher_status to 'disabled'");
        
        return 0;
    }
}