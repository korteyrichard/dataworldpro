<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncFailedOrderStatus extends Command
{
    protected $signature = 'orders:sync-failed';
    protected $description = 'Check failed order pusher status and sync with external APIs';

    public function handle()
    {
        $failedOrders = Order::where('order_pusher_status', 'failed')
            ->where('network', 'mtn')
            ->whereNotNull('reference_id')
            ->get();

        $this->info("Found {$failedOrders->count()} failed MTN orders to check");

        foreach ($failedOrders as $order) {
            try {
                $this->checkMtnOrder($order);
            } catch (\Exception $e) {
                Log::error('Failed to check MTN order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info('Completed checking failed MTN orders');
    }

    private function checkMtnOrder($order)
    {
        try {
            $apiKey = config('app.order_pusher_api_key');
            if (!is_string($apiKey) || $apiKey === '') {
                Log::error('Jaybart order status check skipped because the API key is not configured', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            $configuredBaseUrl = config('app.order_pusher_base_url');
            if (!is_string($configuredBaseUrl) || trim($configuredBaseUrl) === '') {
                Log::error('Jaybart order status check skipped because the base URL is not configured', [
                    'order_id' => $order->id,
                ]);
                return;
            }
            $baseUrl = rtrim($configuredBaseUrl, '/');
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(20)->post($baseUrl . '/fetch-other-network-transaction', [
                'transaction_id' => $order->reference_id
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['order_items']) && is_array($data['order_items']) && count($data['order_items']) > 0) {
                    // Order found on external server, update status to success
                    $order->update(['order_pusher_status' => 'success']);
                    Log::info('Updated failed MTN order to success', ['orderId' => $order->id]);
                    $this->info("Updated order {$order->id} status to success");
                }
            }
        } catch (\Exception $e) {
            Log::error('MTN order check failed', ['orderId' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}