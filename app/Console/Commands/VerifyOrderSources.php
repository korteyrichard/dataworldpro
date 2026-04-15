<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyOrderSources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:verify-sources';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify order sources are properly set';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking order source distribution...');
        
        // Check order source distribution
        $orderSources = DB::table('orders')
            ->select('order_source', DB::raw('count(*) as count'))
            ->groupBy('order_source')
            ->get();

        $this->info('Order Source Distribution:');
        foreach ($orderSources as $source) {
            $this->line("- {$source->order_source}: {$source->count} orders");
        }

        // Check for any orders without order_source
        $nullOrderSource = DB::table('orders')
            ->whereNull('order_source')
            ->count();

        $this->info("\nOrders without order_source: {$nullOrderSource}");

        // Check shop orders specifically
        $shopOrders = DB::table('orders')
            ->where('order_source', 'shop')
            ->count();

        $this->info("Shop orders: {$shopOrders}");

        // Check guest orders with agent_id (should be shop orders)
        $guestOrdersWithAgent = DB::table('orders')
            ->where('is_guest_order', true)
            ->whereNotNull('agent_id')
            ->count();

        $this->info("Guest orders with agent_id: {$guestOrdersWithAgent}");
        
        if ($nullOrderSource > 0) {
            $this->warn("Warning: There are {$nullOrderSource} orders without order_source set!");
        } else {
            $this->info('✅ All orders have order_source properly set!');
        }
        
        return 0;
    }
}
