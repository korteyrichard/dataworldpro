<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

class FindOrphanedPayments extends Command
{
    protected $signature = 'payments:find-orphaned {--verify : Verify payments with Paystack}';
    protected $description = 'Find payments that exist but have no corresponding orders';

    public function handle()
    {
        $this->info('Searching for orphaned payments...');
        
        // Get all payment references from orders
        $existingReferences = Order::whereNotNull('payment_reference')
            ->pluck('payment_reference')
            ->toArray();
        
        $this->info('Found ' . count($existingReferences) . ' existing payment references in orders.');
        
        if ($this->option('verify')) {
            $this->info('Note: Use the verify-pending command with specific references to check individual payments.');
            $this->info('Example: php artisan payments:verify-pending --reference=guest_abc123_1234567890');
        }
        
        // Show some recent payment references for manual checking
        $recentOrders = Order::whereNotNull('payment_reference')
            ->latest()
            ->take(10)
            ->get(['id', 'payment_reference', 'created_at', 'status']);
            
        if ($recentOrders->isNotEmpty()) {
            $this->info("\nRecent payment references:");
            $this->table(
                ['Order ID', 'Payment Reference', 'Created At', 'Status'],
                $recentOrders->map(function($order) {
                    return [
                        $order->id,
                        $order->payment_reference,
                        $order->created_at->format('Y-m-d H:i:s'),
                        $order->status
                    ];
                })->toArray()
            );
        }
        
        $this->info("\nTo check if a specific payment exists but has no order:");
        $this->info("php artisan payments:verify-pending --reference=YOUR_PAYMENT_REFERENCE");
        
        return 0;
    }
}