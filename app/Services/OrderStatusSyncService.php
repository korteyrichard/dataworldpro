<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderStatusSyncService
{
    private $codeCraftAgentEmail = 'YOUR_AGENT_EMAIL';
    private $mtnApiKey = 'YOUR_MTN_API_KEY';

    public function syncOrderStatuses()
    {
        $processingOrders = Order::where('status', 'processing')->get();
        
        foreach ($processingOrders as $order) {
            try {
                if (strtolower($order->network) === 'mtn') {
                    $this->syncMtnOrderStatus($order);
                } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime'])) {
                    $this->syncCodeCraftOrderStatus($order);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncMtnOrderStatus($order)
    {
        $referenceId = $this->extractReferenceId($order);
        if (!$referenceId) {
            Log::warning('No reference ID found for MTN order', ['orderId' => $order->id]);
            return;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->mtnApiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(20)->post('https://agent.jaybartservices.com/api/v1/fetch-other-network-transaction', [
                'transaction_id' => $referenceId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $newStatus = $this->mapMtnStatus($data['status'] ?? '');
                
                if ($newStatus && $newStatus !== $order->status) {
                    $order->update(['status' => $newStatus]);
                    Log::info('MTN order status updated', ['orderId' => $order->id, 'oldStatus' => 'processing', 'newStatus' => $newStatus]);
                }
            }
        } catch (\Exception $e) {
            Log::error('MTN status check failed', ['orderId' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    private function syncCodeCraftOrderStatus($order)
    {
        $referenceId = $this->extractReferenceId($order);
        if (!$referenceId) {
            Log::warning('No reference ID found for order', ['orderId' => $order->id]);
            return;
        }

        $endpoint = 'https://api.codecraftnetwork.com/api/response_agent.php';
        
        try {
            $response = Http::get($endpoint, [
                'client_email' => $this->codeCraftAgentEmail,
                'reference_id' => $referenceId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $newStatus = $this->mapCodeCraftStatus($data['order_status'] ?? '');
                
                if ($newStatus && $newStatus !== $order->status) {
                    $order->update(['status' => $newStatus]);
                    Log::info('Order status updated', ['orderId' => $order->id, 'oldStatus' => 'processing', 'newStatus' => $newStatus]);
                }
            }
        } catch (\Exception $e) {
            Log::error('CodeCraft status check failed', ['orderId' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    private function extractReferenceId($order)
    {
        return $order->reference_id;
    }

    private function mapCodeCraftStatus($externalStatus)
    {
        $statusMap = [
            'Crediting successful' => 'completed',
            'completed' => 'completed',
            'processing' => 'processing',
            'placed' => 'processing',
            'cancelled' => 'cancelled',
            'failed' => 'cancelled'
        ];

        return $statusMap[strtolower($externalStatus)] ?? null;
    }

    private function mapMtnStatus($externalStatus)
    {
        $statusMap = [
            'successful' => 'completed',
            'completed' => 'completed',
            'processing' => 'processing',
            'pending' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled'
        ];

        return $statusMap[strtolower($externalStatus)] ?? null;
    }
}