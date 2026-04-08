<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

class FixGuestOrders extends Command
{
    protected $signature = 'orders:fix-guest-orders';
    protected $description = 'Fix guest orders with missing reference_id or incorrect user_id';

    public function handle()
    {
        $this->info('Starting to fix guest orders...');
        
        // Find guest orders that need fixing
        $guestOrders = Order::where('is_guest_order', true)
            ->where(function($query) {
                $query->whereNull('reference_id')
                      ->orWhere('reference_id', '')
                      ->orWhereNull('user_id');
            })
            ->get();
        
        $this->info("Found {$guestOrders->count()} guest orders to fix.");
        
        $fixed = 0;
        
        foreach ($guestOrders as $order) {
            $updated = false;
            
            // Fix missing reference_id
            if (!$order->reference_id) {
                $order->reference_id = 'guest' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                $updated = true;
                $this->line("Fixed reference_id for order {$order->id}: {$order->reference_id}");
            }
            
            // Fix missing user_id (should be set to agent_id for proper display)
            if (!$order->user_id && $order->agent_id) {
                $order->user_id = $order->agent_id;
                $updated = true;
                $this->line("Fixed user_id for order {$order->id}: set to agent_id {$order->agent_id}");
            }
            
            if ($updated) {
                $order->save();
                $fixed++;
            }
        }
        
        $this->info("Fixed {$fixed} guest orders successfully!");
        
        return 0;
    }
}