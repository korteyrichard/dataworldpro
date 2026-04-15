<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\UserShop;

class TestPaymentFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:payment-flow {reference?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the payment flow and order redirect logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reference = $this->argument('reference');
        
        if (!$reference) {
            // Get a recent guest order for testing
            $order = Order::where('is_guest_order', true)
                ->whereNotNull('payment_reference')
                ->whereNotNull('agent_id')
                ->latest()
                ->first();
                
            if (!$order) {
                $this->error('No guest orders found for testing');
                return 1;
            }
            
            $reference = $order->payment_reference;
            $this->info("Using reference from order #{$order->id}: {$reference}");
        }
        
        $this->info("Testing payment flow for reference: {$reference}");
        
        // Test the order lookup
        $order = Order::where('payment_reference', $reference)
            ->where('is_guest_order', true)
            ->first();
            
        if (!$order) {
            $this->error('Order not found with this reference');
            return 1;
        }
        
        $this->info("✅ Order found: #{$order->id}");
        $this->info("   - Agent ID: {$order->agent_id}");
        $this->info("   - User ID: {$order->user_id}");
        $this->info("   - Status: {$order->status}");
        $this->info("   - Total: GHS {$order->total}");
        
        // Test shop lookup
        if ($order->agent_id) {
            $shop = UserShop::where('user_id', $order->agent_id)->first();
            
            if ($shop) {
                $this->info("✅ Shop found: {$shop->name} (slug: {$shop->slug})");
                
                // Test the success route
                $successUrl = route('shop.order-success', [
                    'slug' => $shop->slug,
                    'order' => $order->id
                ]);
                
                $this->info("✅ Success URL: {$successUrl}");
                
                return 0;
            } else {
                $this->error('❌ Shop not found for agent_id: ' . $order->agent_id);
                return 1;
            }
        } else {
            $this->error('❌ Order has no agent_id');
            return 1;
        }
    }
}
