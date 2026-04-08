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

    private $mtnApiKey = 'b2fe77274d245a52c7bf4c03ba96f46c2bed9be3';

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
            $response = Http::withHeaders([
                'x-api-key' => $this->mtnApiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(20)->post('https://agent.jaybartservices.com/api/v1/fetch-other-network-transaction', [
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