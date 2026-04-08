<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\PaystackGuestService;

class VerifyPendingPayments extends Command
{
    protected $signature = 'payments:verify-pending {--reference= : Specific payment reference to verify}';
    protected $description = 'Verify and complete pending Paystack payments';

    public function handle()
    {
        $reference = $this->option('reference');
        
        if ($reference) {
            $this->verifySpecificPayment($reference);
        } else {
            $this->info('Please provide a payment reference using --reference option');
            $this->info('Example: php artisan payments:verify-pending --reference=guest_abc123_1234567890');
        }
        
        return 0;
    }
    
    private function verifySpecificPayment($reference)
    {
        $this->info("Verifying payment reference: {$reference}");
        
        // Check if order already exists
        $existingOrder = Order::where('payment_reference', $reference)->first();
        if ($existingOrder) {
            $this->info("Order already exists for this reference: Order ID {$existingOrder->id}");
            return;
        }
        
        // Try to verify with Paystack
        $paystackService = new PaystackGuestService();
        $result = $paystackService->verifyPayment($reference);
        
        if ($result['status']) {
            $this->info("Payment verified successfully!");
            if (isset($result['order'])) {
                $this->info("Order created: ID {$result['order']->id}");
                $this->info("Reference ID: {$result['order']->reference_id}");
                $this->info("Shop: {$result['shop_slug']}");
            }
        } else {
            $this->error("Payment verification failed: " . ($result['message'] ?? 'Unknown error'));
        }
    }
}