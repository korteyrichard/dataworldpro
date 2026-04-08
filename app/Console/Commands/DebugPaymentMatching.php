<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AgentShop;
use App\Services\AgentService;
use Illuminate\Support\Facades\Http;

class DebugPaymentMatching extends Command
{
    protected $signature = 'debug:payment-matching {shop_slug} {payment_reference}';
    protected $description = 'Debug payment matching for a specific shop and payment reference';

    public function handle()
    {
        $shopSlug = $this->argument('shop_slug');
        $paymentReference = $this->argument('payment_reference');
        
        $this->info("Debugging payment matching for shop: {$shopSlug}");
        $this->info("Payment reference: {$paymentReference}");
        
        // Find the shop
        $shop = AgentShop::where('slug', $shopSlug)->first();
        if (!$shop) {
            $this->error("Shop not found with slug: {$shopSlug}");
            return 1;
        }
        
        $this->info("Shop found: {$shop->name} (ID: {$shop->id})");
        
        // Verify payment
        $secretKey = config('services.paystack.secret_key');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->get("https://api.paystack.co/transaction/verify/{$paymentReference}");
        
        if (!$response->successful()) {
            $this->error("Failed to verify payment with Paystack");
            return 1;
        }
        
        $paymentData = $response->json()['data'];
        if ($paymentData['status'] !== 'success') {
            $this->error("Payment status is not success: " . $paymentData['status']);
            return 1;
        }
        
        $paymentAmount = $paymentData['amount'] / 100;
        $this->info("Payment amount: GHS {$paymentAmount}");
        $this->info("Payment email: " . $paymentData['customer']['email']);
        
        // Get shop products
        $agentService = new AgentService();
        $products = $agentService->getShopProducts($shop);
        
        $this->info("Total products in shop: " . $products->count());
        
        if ($products->isEmpty()) {
            $this->error("No products found in this shop");
            return 1;
        }
        
        // Show all products with prices
        $this->info("\nAll products in shop:");
        $this->table(
            ['ID', 'Name', 'Network', 'Agent Price', 'Difference', 'Matches'],
            $products->map(function($product) use ($paymentAmount) {
                $productPrice = floatval($product['agent_price']);
                $difference = abs($productPrice - $paymentAmount);
                $matches = $difference < 0.01 ? 'YES' : 'NO';
                
                return [
                    $product['id'],
                    $product['name'],
                    $product['network'] ?? 'N/A',
                    'GHS ' . number_format($productPrice, 2),
                    number_format($difference, 4),
                    $matches
                ];
            })->toArray()
        );
        
        // Find matching products
        $matchingProducts = $products->filter(function($product) use ($paymentAmount) {
            return abs(floatval($product['agent_price']) - $paymentAmount) < 0.01;
        });
        
        $this->info("\nMatching products: " . $matchingProducts->count());
        
        if ($matchingProducts->isNotEmpty()) {
            $this->info("Products that match the payment amount:");
            foreach ($matchingProducts as $product) {
                $this->line("- {$product['name']} (GHS {$product['agent_price']})");
            }
        }
        
        return 0;
    }
}