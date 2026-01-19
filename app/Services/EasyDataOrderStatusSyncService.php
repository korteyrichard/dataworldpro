<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EasyDataOrderStatusSyncService
{
    private $baseUrl;
    private $username;
    private $password;

    public function __construct()
    {
        $this->baseUrl = config('services.easydata.base_url');
        $this->username = config('services.easydata.username');
        $this->password = config('services.easydata.password');
    }

    public function syncOrderStatuses()
    {
        Log::info('Starting EasyData order status sync');

        $orders = Order::where('network', 'MTN')
            ->whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->where('order_pusher_status', 'success')
            ->get();

        Log::info('Found orders to sync', ['count' => $orders->count()]);

        foreach ($orders as $order) {
            // For EasyData, we need to use the original order reference we sent, not their order_id
            $orderReference = 'ORDER_' . $order->id . '_' . $order->products->first()->id;
            $this->syncOrderStatus($order, $orderReference);
        }

        Log::info('EasyData order status sync completed');
    }

    private function syncOrderStatus(Order $order, $orderReference)
    {
        Log::info('Syncing order status', [
            'order_id' => $order->id, 
            'reference_id' => $order->reference_id,
            'order_reference' => $orderReference
        ]);

        try {
            $credentials = base64_encode($this->username . ':' . $this->password);
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ])->timeout(30)->get($this->baseUrl . '/order-status', [
                'order_reference' => $orderReference
            ]);

            Log::info('EasyData status API response', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['status']) && $responseData['status'] === 'success' && 
                    isset($responseData['order_status'])) {
                    
                    $apiStatus = strtolower($responseData['order_status']);
                    $newStatus = $this->mapApiStatusToOrderStatus($apiStatus);
                    
                    if ($newStatus && $order->status !== $newStatus) {
                        $order->update(['status' => $newStatus]);
                        
                        Log::info('Order status updated', [
                            'order_id' => $order->id,
                            'old_status' => $order->status,
                            'new_status' => $newStatus,
                            'api_status' => $apiStatus
                        ]);
                    }
                } else {
                    Log::warning('Invalid EasyData status response', [
                        'order_id' => $order->id,
                        'response' => $responseData
                    ]);
                }
            } else {
                Log::error('EasyData status API failed', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('EasyData status sync error', [
                'order_id' => $order->id,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function mapApiStatusToOrderStatus($apiStatus)
    {
        $statusMap = [
            'completed' => 'completed',
            'success' => 'completed',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled',
            'pending' => 'pending',
            'processing' => 'processing'
        ];

        return $statusMap[$apiStatus] ?? null;
    }
}